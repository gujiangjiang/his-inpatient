<?php
// api/modules/patients/patient_discharge.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) {
    Response::error('请输入患者ID');
}

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ? AND status = 'active'", [$id]);
if (!$patient) {
    Response::error('患者不存在或已出院');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE patients SET status = 'discharged', bed_no = NULL WHERE id = ?");
    $stmt->execute([$id]);

    // 释放床位
    if ($patient['bed_no'] && $patient['ward_id']) {
        $bedStmt = $pdo->prepare("UPDATE beds SET status = 'free', patient_id = NULL WHERE ward_id = ? AND bed_no = ?");
        $bedStmt->execute([$patient['ward_id'], $patient['bed_no']]);
    }

    // 记录出院记录
    $stmt = $pdo->prepare("INSERT INTO discharge_records (patient_id, admission_no, discharge_diagnosis, discharge_icd_code, discharge_advice, discharged_at, discharged_by, created_at) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $id,
        $patient['admission_no'],
        $data['discharge_diagnosis'] ?? '',
        $data['discharge_icd_code'] ?? '',
        $data['discharge_advice'] ?? '',
        DateHelper::now(),
        Auth::user()['id'],
        DateHelper::now()
    ]);

    $pdo->commit();
    Response::success(['id' => $id]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('出院失败：' . $e->getMessage());
}
