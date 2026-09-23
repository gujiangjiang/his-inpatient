<?php
// api/modules/patients/patient_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$name = Validator::get('name');
$gender = Validator::get('gender');
$birthDate = Validator::get('birth_date');
$departmentId = Validator::get('department_id');
$wardId = Validator::get('ward_id');

if (!$name || !$gender || !$birthDate || !$departmentId || !$wardId) {
    Response::error('请填写完整患者信息');
}

$pdo = DB::getPDO();
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE patients SET name=?, gender=?, birth_date=?, id_card=?, department_id=?, ward_id=?, bed_no=?, admission_date=?, admission_diagnosis=?, admission_icd_code=? WHERE id=?");
    $stmt->execute([
        $name, $gender, $birthDate,
        $data['id_card'] ?? '',
        $departmentId, $wardId,
        $data['bed_no'] ?? '',
        $data['admission_date'] ?? DateHelper::now(),
        $data['admission_diagnosis'] ?? '',
        $data['admission_icd_code'] ?? '',
        $id
    ]);
    Response::success(['id' => $id]);
} else {
    $patientNo = 'P' . date('Ymd') . str_pad($pdo->query("SELECT COUNT(*)+1 FROM patients")->fetchColumn(), 4, '0', STR_PAD_LEFT);
    $admissionNo = 'A' . date('Ymd') . str_pad($pdo->query("SELECT COUNT(*)+1 FROM patients")->fetchColumn(), 4, '0', STR_PAD_LEFT);

    if (!empty($data['bed_no'])) {
        $bedStmt = $pdo->prepare("SELECT * FROM beds WHERE ward_id = ? AND bed_no = ? AND status = 'occupied'");
        $bedStmt->execute([$wardId, $data['bed_no']]);
        if ($bedStmt->fetch()) {
            Response::error('床位已被占用');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO patients (patient_no, name, gender, birth_date, id_card, admission_no, department_id, ward_id, bed_no, admission_date, admission_diagnosis, admission_icd_code, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patientNo, $name, $gender, $birthDate,
        $data['id_card'] ?? '',
        $admissionNo, $departmentId, $wardId,
        $data['bed_no'] ?? '',
        $data['admission_date'] ?? DateHelper::now(),
        $data['admission_diagnosis'] ?? '',
        $data['admission_icd_code'] ?? '',
        'active',
        DateHelper::now()
    ]);
    $newId = $pdo->lastInsertId();

    if (!empty($data['bed_no'])) {
        $bedUpdate = $pdo->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE ward_id = ? AND bed_no = ?");
        $bedUpdate->execute([$newId, $wardId, $data['bed_no']]);
    }

    Response::success(['id' => $newId, 'patient_no' => $patientNo, 'admission_no' => $admissionNo]);
}
