<?php
// api/modules/nurse/admission/leave_ward.php - 护士移出科室 (转科/出院共用)
// 转科/出院都是从本科室离开: 出院无目标科室, 转科有目标科室
// 护士无处方权不停止医嘱: 若存在未停止医嘱则提示, 由医生先停止
// 校验: 医嘱已全停 + 无未核对医嘱 + 费用核对(前端确认前完成) → 释放床位 + 标记患者
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$mode = Validator::get('mode', 'transfer');       // transfer 转科 | discharge 出院
$targetWardId = (int)Validator::get('target_ward_id', 0);
if (!$patientId) Response::error('请选择患者');
if ($mode === 'transfer' && !$targetWardId) Response::error('请选择目标科室');
if (!in_array($mode, ['transfer', 'discharge'], true)) Response::error('移出方式无效');

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ? AND status = 'active'", [$patientId]);
if (!$patient) Response::error('患者不存在或已离科');
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);

$targetWard = null;
if ($mode === 'transfer') {
    $targetWard = DB::selectOne("SELECT * FROM wards WHERE id = ? AND is_active = 1", [$targetWardId]);
    if (!$targetWard) Response::error('目标科室不存在');
}

/* 校验: 1. 无未核对医嘱 (草稿/待核/停止待核/作废待核) */
$blockOrders = DB::select("SELECT COUNT(*) n FROM orders WHERE patient_id = ? AND status IN ('draft','pending','stop_pending','invalid_pending')", [$patientId]);
if ((int)$blockOrders[0]['n'] > 0) Response::error('存在未核对的医嘱（共 ' . (int)$blockOrders[0]['n'] . ' 条），请护士核对后再办理', 409);

/* 校验: 2. 医嘱已全部停止 (护士无处方权不停止医嘱, 未停止则提醒由医生操作) */
$notStopped = DB::select("SELECT COUNT(*) n FROM orders WHERE patient_id = ? AND status IN ('pending','verified','executing')", [$patientId]);
if ((int)$notStopped[0]['n'] > 0) {
    $periods = DB::select("SELECT order_period, COUNT(*) n FROM orders WHERE patient_id = ? AND status IN ('pending','verified','executing') GROUP BY order_period", [$patientId]);
    $parts = [];
    foreach ($periods as $pp) {
        $label = $pp['order_period'] === 'long' ? '长期医嘱' : '临时医嘱';
        $parts[] = $label . ' ' . (int)$pp['n'] . ' 条';
    }
    Response::error('存在未停止的医嘱（' . implode('、', $parts) . '），请医生先停止后再办理', 409);
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $userId = $user['id'];

    // 1. 释放床位 (仅释放自己占用的床)
    if (!empty($patient['bed_no']) && !empty($patient['ward_id'])) {
        DB::execute("UPDATE beds SET status = 'free', patient_id = NULL WHERE ward_id = ? AND bed_no = ? AND patient_id = ?",
            [$patient['ward_id'], $patient['bed_no'], $patientId]);
    }

    // 2. 更新患者状态
    if ($mode === 'transfer') {
        DB::execute("UPDATE patients SET status = 'transferred', transferred_to = ?, bed_no = NULL, updated_at = ? WHERE id = ?",
            [$targetWard['name'], $now, $patientId]);
    } else {
        DB::execute("UPDATE patients SET status = 'discharged', bed_no = NULL, updated_at = ? WHERE id = ?",
            [$now, $patientId]);
    }

    // 3. 护理记录流水
    if ($mode === 'transfer') {
        DB::execute(
            "INSERT INTO nursing_notes (patient_id, admission_no, record_time, category, content, note, recorded_by, created_at)
             VALUES (?,?,?, 'transfer', ?, ?, ?, ?)",
            [$patientId, $patient['admission_no'], $now,
             '转科：' . ($patient['ward_id'] ? '本科室 → ' : '') . $targetWard['name'],
             '护士转科：患者转至 ' . $targetWard['name'] . '，医嘱已全部停止',
             $userId, $now]);
    } else {
        DB::execute(
            "INSERT INTO nursing_notes (patient_id, admission_no, record_time, category, content, note, recorded_by, created_at)
             VALUES (?,?,?, 'other', ?, ?, ?, ?)",
            [$patientId, $patient['admission_no'], $now,
             '出院离科',
             '护士办理：患者出院离科，医嘱已全部停止',
             $userId, $now]);
    }

    $pdo->commit();
    if ($mode === 'transfer') Response::success([], '已转科至 ' . $targetWard['name']);
    else Response::success([], '已办理出院，患者离科');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    Response::error('办理失败：' . $e->getMessage());
}
