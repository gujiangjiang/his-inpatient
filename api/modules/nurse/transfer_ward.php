<?php
// api/modules/nurse/transfer_ward.php - 护士转科 (患者从本科室转出到其他科室)
// 转科 = 本科室离开操作: 全部医嘱停止 + 释放床位 + 患者标记转科 (类似出院流程)
// 目标科室: 选择目标科室(病区); 费用核对由前端在确认前完成
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$targetWardId = (int)Validator::get('target_ward_id', 0);
if (!$patientId || !$targetWardId) Response::error('请选择患者与目标科室');

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ? AND status = 'active'", [$patientId]);
if (!$patient) Response::error('患者不存在或已出院');
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
$targetWard = DB::selectOne("SELECT * FROM wards WHERE id = ? AND is_active = 1", [$targetWardId]);
if (!$targetWard) Response::error('目标科室不存在');

// 转科前校验: 无待核对医嘱 (与出院一致)
$blockOrders = DB::select("SELECT COUNT(*) n FROM orders WHERE patient_id = ? AND status IN ('draft','pending','stop_pending','invalid_pending')", [$patientId]);
if ((int)$blockOrders[0]['n'] > 0) Response::error('存在未核对医嘱（共 ' . (int)$blockOrders[0]['n'] . ' 条），请护士核对后再转科', 409);

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $userId = $user['id'];

    // 1. 全部未停止医嘱停止 (长期+临时, 排除已作废/已停止)
    $stopped = DB::execute(
        "UPDATE orders SET status = 'stopped', stop_date = ?, stopped_by = ?, updated_at = ?
         WHERE patient_id = ? AND status IN ('pending','verified','executing')",
        [$now, $userId, $now, $patientId]);

    // 2. 释放床位
    if (!empty($patient['bed_no']) && !empty($patient['ward_id'])) {
        DB::execute("UPDATE beds SET status = 'free', patient_id = NULL WHERE ward_id = ? AND bed_no = ?",
            [$patient['ward_id'], $patient['bed_no']]);
    }

    // 3. 更新患者: 标记转科 (保留科室信息, 状态=transferred 离开本科室)
    DB::execute("UPDATE patients SET status = 'transferred', transferred_to = ?, bed_no = NULL, updated_at = ? WHERE id = ?",
        [$targetWard['name'], $now, $patientId]);

    // 4. 转科护理记录流水
    DB::execute(
        "INSERT INTO nursing_notes (patient_id, admission_no, record_time, category, content, note, recorded_by, created_at)
         VALUES (?,?,?, 'transfer', ?, ?, ?, ?)",
        [$patientId, $patient['admission_no'], $now,
         '转科：' . ($patient['ward_id'] ? '本科室 → ' : '') . $targetWard['name'],
         '护士转科：患者转至 ' . $targetWard['name'] . '，全部医嘱已停止',
         $userId, $now]);

    $pdo->commit();
    Response::success(['stopped' => $stopped], '已转科至 ' . $targetWard['name']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    Response::error('转科失败：' . $e->getMessage());
}
