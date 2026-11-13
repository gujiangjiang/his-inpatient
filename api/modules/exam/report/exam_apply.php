<?php
// api/modules/exam/exam_apply.php - 检查申请: 生成检查申请单 + 对应医嘱
// 说明: 申请类自动生成的医嘱一律直接提交 (pending 待护士核对), 无草稿态
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$itemIds = Validator::get('item_ids', []);
if (!$patientId || !is_array($itemIds) || !count($itemIds)) Response::error('缺少患者或检查项目');

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
if (!patient_admitted_at($patientId)) Response::error('患者尚未入科 (需先指派主管医师)');

$ids = array_values(array_unique(array_map('intval', $itemIds)));
$ph = implode(',', array_fill(0, count($ids), '?'));
$items = DB::select("SELECT * FROM exam_catalog WHERE is_active = 1 AND id IN ($ph) ORDER BY sort_no, id", $ids);
if (!$items) Response::error('未找到可用的检查项目');

// 申请类医嘱默认直接提交, 待护士核对 (无草稿)
$status = 'pending';
$now = DateHelper::now();
$nowLocal = TimeHelper::nowLocal();   // 业务日期时间字段（start_date 等）按本地墙钟
$base = (int)DB::selectOne("SELECT COUNT(*) as c FROM exam_reports")['c'];
$pdo = DB::getPDO();
$opdPush = ['processed' => 0, 'success' => 0, 'failed' => 0];
$pdo->beginTransaction();
try {
    $reportIds = [];
    $orderIds = [];
    $i = 0;
    foreach ($items as $it) {
        $i++;
        $reportNo = 'EXAM' . date('Ymd') . str_pad((string)($base + $i), 4, '0', STR_PAD_LEFT);
        $reportId = (int)DB::insert('exam_reports', [
            'patient_id' => $patientId,
            'admission_no' => $patient['admission_no'],
            'report_no' => $reportNo,
            'exam_type' => $it['name'],
            'body_part' => $it['sub_category'] ?? '',
            'status' => 'pending',
            'created_by' => $user['id'],
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
            'is_auto_generated' => 1,
            'notes' => $it['dept'] ?? '',
            'start_date' => $nowLocal,
            'created_by' => $user['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::execute("UPDATE exam_reports SET order_ids = ? WHERE id = ?", [json_encode([$orderId]), $reportId]);
        $reportIds[] = $reportId;
        $orderIds[] = $orderId;
    }
    $pdo->commit();
    // 住院检查单推送门诊（登记摄片出报告）：住院开单即计费，一律视为已缴费；失败入队重试
    if (opd_order_push_enabled()) {
        try {
            foreach ($reportIds as $opdRid) opd_order_push_submit('exam', (int)$opdRid);
            $opdPush = opd_order_push_process(count($reportIds) + 10);
        } catch (Exception $opdEx) {
            $opdPush['failed'] = count($reportIds);
        }
    }
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('生成检查申请失败: ' . $e->getMessage(), 500);
}
Response::success([
    'reports' => count($reportIds),
    'orders' => count($orderIds),
    'report_ids' => $reportIds,
    'order_ids' => $orderIds,
    'opd_push' => $opdPush,
], '已提交 ' . count($items) . ' 条检查医嘱（待护士核对）');
