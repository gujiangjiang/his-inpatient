<?php
// api/modules/lab/lab_apply.php - 检验申请: 生成检验申请单 + 对应医嘱 (带时间戳)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$itemIds = Validator::get('item_ids', []);
$submit = (int)Validator::get('submit', 0);
if (!$patientId || !is_array($itemIds) || !count($itemIds)) Response::error('缺少患者或检验项目');

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
if (!patient_admitted_at($patientId)) Response::error('患者尚未入科 (需先指派主管医师)');

$ids = array_values(array_unique(array_map('intval', $itemIds)));
$ph = implode(',', array_fill(0, count($ids), '?'));
$items = DB::select("SELECT * FROM lab_catalog WHERE is_active = 1 AND id IN ($ph) ORDER BY sort_no, id", $ids);
if (!$items) Response::error('未找到可用的检验项目');

$status = $submit ? 'pending' : 'draft';
$now = DateHelper::now();
$base = (int)DB::selectOne("SELECT COUNT(*) as c FROM lab_reports")['c'];
$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $reportIds = [];
    $orderIds = [];
    $i = 0;
    foreach ($items as $it) {
        $i++;
        $reportNo = 'LAB' . date('Ymd') . str_pad((string)($base + $i), 4, '0', STR_PAD_LEFT);
        $reportId = (int)DB::insert('lab_reports', [
            'patient_id' => $patientId,
            'admission_no' => $patient['admission_no'],
            'report_no' => $reportNo,
            'exam_name' => $it['name'],
            'specimen_type' => $it['specimen'] ?? '',
            'status' => 'pending',
            'created_by' => $user['id'],
            'created_at' => $now,
        ]);
        DB::insert('lab_report_items', [
            'report_id' => $reportId,
            'item_name' => $it['name'],
            'created_at' => $now,
        ]);
        $orderNo = 'O' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $orderId = (int)DB::insert('orders', [
            'patient_id' => $patientId,
            'admission_no' => $patient['admission_no'],
            'order_type' => 'general',
            'order_period' => 'temp',
            'order_no' => $orderNo,
            'content' => $it['name'],
            'category' => 'nonmedication',
            'status' => $status,
            'notes' => $it['note'] ?? '',
            'start_date' => $now,
            'created_by' => $user['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::execute("UPDATE lab_reports SET order_ids = ? WHERE id = ?", [json_encode([$orderId]), $reportId]);
        $reportIds[] = $reportId;
        $orderIds[] = $orderId;
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('生成检验申请失败: ' . $e->getMessage(), 500);
}
Response::success([
    'reports' => count($reportIds),
    'orders' => count($orderIds),
    'report_ids' => $reportIds,
    'order_ids' => $orderIds,
], '已生成 ' . count($items) . ' 条检验医嘱');
