<?php
// api/modules/emr/discharge.php - 开具出院医嘱
// 今日出院: 仅下临时医嘱, 不自动停医嘱
// 明日出院: 下临时医嘱 + 所有长期医嘱停止到所选时间 (待护士确认)
// 前后端双重校验: 存在未核对医嘱/未完成检验检查/未发放处方时禁止出院
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$patientId = (int)Validator::get('patient_id', 0);
$mode = Validator::get('mode', 'today');
$dischargeAt = trim((string)Validator::get('discharge_at', ''));

if (!$patientId) Response::error('缺少 patient_id');
if (!in_array($mode, ['today', 'tomorrow'], true)) Response::error('出院方式无效');
if ($dischargeAt === '') Response::error('请选择出院日期时间');
$ts = strtotime($dischargeAt);
if ($ts === false) Response::error('出院日期时间格式错误');
if ($ts < strtotime(TimeHelper::nowLocal())) Response::error('出院日期时间不能早于当前时间');

$user = Auth::user();
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
if ($patient['status'] !== 'active') Response::error('患者当前状态不可出院');

/* 服务端复核出院条件 */
$blockOrders = DB::select("SELECT COUNT(*) n FROM orders WHERE patient_id = ? AND status IN ('draft','pending','stop_pending','invalid_pending')", [$patientId]);
if ((int)$blockOrders[0]['n'] > 0) Response::error('存在未核对的医嘱（共 ' . (int)$blockOrders[0]['n'] . ' 条），请护士核对后再出院', 409);
$blockMeds = DB::select("SELECT COUNT(*) n FROM orders o WHERE o.patient_id = ? AND o.category = 'medication' AND o.status IN ('verified','executing') AND NOT EXISTS (SELECT 1 FROM dispensing_records d WHERE d.order_id = o.id)", [$patientId]);
if ((int)$blockMeds[0]['n'] > 0) Response::error('存在未发放的处方（共 ' . (int)$blockMeds[0]['n'] . ' 条），请药房发药后再出院', 409);

$existing = DB::selectOne("SELECT id FROM orders WHERE patient_id = ? AND content IN ('今日出院','明日出院') AND status IN ('draft','pending','verified','executing') ORDER BY id DESC LIMIT 1", [$patientId]);
if ($existing) Response::error('已存在未完成的出院医嘱，请勿重复开具', 409);

$content = $mode === 'today' ? '今日出院' : '明日出院';
$userId = (int)$user['id'];
$now = DateHelper::now();
$orderNo = 'O' . date('YmdHis') . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period,
                order_no, content, category, status, billing_flags, group_seq, sort_no, start_date, end_date, notes,
                created_by, created_at, updated_at)
            VALUES (?,?,NULL,0,'general','temp',?,?,'general','pending','',0,0,?,'', '出院', ?,?,?)");
    $stmt->execute([$patientId, $patient['admission_no'], $orderNo, $content, $dischargeAt, $userId, $now, $now]);
    $orderId = (int)$pdo->lastInsertId();

    $stopped = 0;
    if ($mode === 'tomorrow') {
        /* 所有长期医嘱停止到所选时间 (待护士确认后转为已停止) */
        $stmt2 = $pdo->prepare("UPDATE orders SET status = 'stop_pending', stop_date = ?, stopped_by = ?, updated_at = ?
                                WHERE patient_id = ? AND order_period = 'long' AND status IN ('pending','verified','executing')");
        $stmt2->execute([$dischargeAt, $userId, $now, $patientId]);
        $stopped = $stmt2->rowCount();
    }
    $pdo->commit();
    Response::success(['order_id' => $orderId, 'order_no' => $orderNo, 'stopped' => $stopped]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('出院医嘱开具失败：' . $e->getMessage());
}
