<?php
// api/modules/lab/lab_apply.php - 检验申请: 生成检验申请单 + 对应医嘱
// 说明: 申请类自动生成的医嘱一律直接提交 (pending 待护士核对), 无草稿态
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$itemIds = Validator::get('item_ids', []);
if (!$patientId || !is_array($itemIds) || !count($itemIds)) Response::error('缺少患者或检验项目');

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
if (!patient_admitted_at($patientId)) Response::error('患者尚未入科 (需先指派主管医师)');

$ids = array_values(array_unique(array_map('intval', $itemIds)));
$ph = implode(',', array_fill(0, count($ids), '?'));
$items = DB::select("SELECT * FROM lab_catalog WHERE is_active = 1 AND id IN ($ph) ORDER BY sort_no, id", $ids);
if (!$items) Response::error('未找到可用的检验项目');

// 申请类医嘱默认直接提交, 待护士核对 (无草稿)
$status = 'pending';
$now = DateHelper::now();
$nowLocal = TimeHelper::nowLocal();   // 业务日期时间字段（start_date 等）按本地墙钟
$base = (int)DB::selectOne("SELECT COUNT(*) as c FROM lab_reports")['c'];
$pdo = DB::getPDO();
$opdPush = ['processed' => 0, 'success' => 0, 'failed' => 0];
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
        // 组合项目: 申请单明细展开为组合成员 (名称/单位/参考范围来自门诊字典)
        $members = json_decode((string)($it['members_json'] ?? ''), true) ?: [];
        if ($members) {
            foreach ($members as $mem) {
                if (trim((string)($mem['name'] ?? '')) === '') continue;
                DB::insert('lab_report_items', [
                    'report_id' => $reportId,
                    'item_name' => (string)$mem['name'],
                    'unit' => (string)($mem['unit'] ?? ''),
                    'reference_range' => (string)($mem['normal_range'] ?? ''),
                    'created_at' => $now,
                ]);
            }
        } else {
            DB::insert('lab_report_items', [
                'report_id' => $reportId,
                'item_name' => $it['name'],
                'created_at' => $now,
            ]);
        }
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
            'notes' => $it['note'] ?? '',
            'start_date' => $nowLocal,
            'created_by' => $user['id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::execute("UPDATE lab_reports SET order_ids = ? WHERE id = ?", [json_encode([$orderId]), $reportId]);
        $reportIds[] = $reportId;
        $orderIds[] = $orderId;
    }
    $pdo->commit();
    // 住院检验单推送门诊（登记出结果）：住院开单即计费，一律视为已缴费；失败入队重试
    if (opd_order_push_enabled()) {
        try {
            foreach ($reportIds as $opdRid) opd_order_push_submit('lab', (int)$opdRid);
            $opdPush = opd_order_push_process(count($reportIds) + 10);
        } catch (Exception $opdEx) {
            $opdPush['failed'] = count($reportIds);
        }
    }
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('生成检验申请失败: ' . $e->getMessage(), 500);
}
Response::success([
    'reports' => count($reportIds),
    'orders' => count($orderIds),
    'report_ids' => $reportIds,
    'order_ids' => $orderIds,
    'opd_push' => $opdPush,
], '已提交 ' . count($items) . ' 条检验医嘱（待护士核对）');
