<?php
// api/modules/nurse/transfer_bed.php - 护士转床 (床位一览表内操作)
// 护士仅可转本病区患者到病区内床位; 目标床位必须空闲
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$targetBedId = (int)Validator::get('target_bed_id', 0);
if (!$patientId || !$targetBedId) Response::error('请选择患者与目标床位');

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT id, ward_id, bed_no, admission_no FROM patients WHERE id = ? AND status = 'active'", [$patientId]);
if (!$patient) Response::error('患者不存在或已出院');
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);

$target = DB::selectOne("SELECT * FROM beds WHERE id = ?", [$targetBedId]);
if (!$target) Response::error('目标床位不存在');
if ($target['status'] === 'occupied' && !empty($target['patient_id'])) Response::error('目标床位已被占用');

// 目标床位所在病区: 护士须为同病区; admin 任意
if ($user['role'] === 'nurse' && (int)$target['ward_id'] !== (int)$user['ward_id']) {
    Response::error('无权转床到其他病区', 403);
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    // 释放原床位 (若患者当前有床位)
    if (!empty($patient['bed_no']) && !empty($patient['ward_id'])) {
        DB::execute("UPDATE beds SET status = 'free', patient_id = NULL WHERE ward_id = ? AND bed_no = ?",
            [$patient['ward_id'], $patient['bed_no']]);
    }
    // 占用目标床位 (原子条件, 防并发)
    $aff = DB::execute("UPDATE beds SET status = 'occupied', patient_id = ? WHERE id = ? AND status = 'free'",
        [$patientId, $targetBedId]);
    if ($aff !== 1) {
        $pdo->rollback();
        Response::error('目标床位已被占用，请重新选择');
    }
    // 更新患者床位
    DB::execute("UPDATE patients SET ward_id = ?, bed_no = ? WHERE id = ?",
        [$target['ward_id'], $target['bed_no'], $patientId]);
    // 转科记录流水 (护理记录单: 转科)
    $wardName = DB::selectOne("SELECT name FROM wards WHERE id = ?", [$target['ward_id']])['name'] ?? '';
    DB::execute("INSERT INTO nursing_notes (patient_id, admission_no, record_time, category, content, recorded_by, created_at)
                 VALUES (?,?,?, 'transfer', ?, ?, ?)",
        [$patientId, (string)($patient['admission_no'] ?? ''), $now,
         '护士转床：' . ($patient['bed_no'] ? $patient['bed_no'] . '床 → ' : '') . $target['bed_no'] . '床（' . $wardName . '）',
         $user['id'], $now]);
    $pdo->commit();
    Response::success([], '已转床至 ' . $target['bed_no'] . ' 床');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    Response::error('转床失败：' . $e->getMessage());
}