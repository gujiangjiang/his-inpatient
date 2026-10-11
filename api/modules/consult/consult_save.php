<?php
// api/modules/consult/consult_save.php - 发起会诊: 生成"请X科会诊"医嘱 (带时间戳)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$toDeptId = (int)Validator::get('to_dept_id', 0);
$purpose = trim((string)Validator::get('purpose', ''));
if (!$patientId || !$toDeptId || $purpose === '') Response::error('请选择目标科室并填写会诊目的');

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);

$toDept = DB::selectOne("SELECT * FROM departments WHERE id = ? AND is_active = 1", [$toDeptId]);
if (!$toDept) Response::error('目标科室不存在或已停用');
$fromDeptId = (int)($user['department_id'] ?? 0);
if ($fromDeptId && $toDeptId === $fromDeptId) Response::error('不能向本科室发起会诊');

$dup = DB::selectOne(
    "SELECT id FROM consultations WHERE patient_id = ? AND to_dept_id = ? AND status = 'pending'",
    [$patientId, $toDeptId]
);
if ($dup) Response::error('该科室对该患者已有进行中的会诊申请');

$fromDeptName = $fromDeptId
    ? (string)(DB::selectOne("SELECT name FROM departments WHERE id = ?", [$fromDeptId])['name'] ?? '')
    : '';
$now = DateHelper::now();
$content = '请' . $toDept['name'] . '会诊';

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $orderNo = 'O' . date('YmdHis') . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    $orderId = (int)DB::insert('orders', [
        'patient_id' => $patientId,
        'admission_no' => $patient['admission_no'],
        'order_type' => 'general',
        'order_period' => 'temp',
        'order_no' => $orderNo,
        'content' => $content,
        'category' => 'nonmedication',
        'status' => 'pending',
        'notes' => $purpose,
        'start_date' => $now,
        'created_by' => $user['id'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $consultId = (int)DB::insert('consultations', [
        'patient_id' => $patientId,
        'admission_no' => $patient['admission_no'],
        'from_dept_id' => $fromDeptId,
        'from_dept_name' => $fromDeptName,
        'to_dept_id' => $toDeptId,
        'to_dept_name' => $toDept['name'],
        'purpose' => $purpose,
        'status' => 'pending',
        'created_by' => $user['id'],
        'created_by_name' => $user['name'],
        'order_id' => $orderId,
        'created_at' => $now,
    ]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('会诊申请失败: ' . $e->getMessage(), 500);
}
Response::success(['id' => $consultId, 'order_id' => $orderId], '会诊申请已发送');
