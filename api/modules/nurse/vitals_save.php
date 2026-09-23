<?php
// api/modules/nurse/vitals_save.php
Auth::requireRole(['admin', 'nurse']);
$data = Validator::all();
$patientId = Validator::get('patient_id');

if (!$patientId) Response::error('请选择患者');

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');

$stmt = $pdo->prepare("INSERT INTO vital_signs (patient_id, admission_no, recorded_by, recorded_at, temperature, pulse, blood_pressure_systolic, blood_pressure_diastolic, respiratory_rate, oxygen_saturation, height, weight, notes, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([
    $patientId, $patient['admission_no'], Auth::user()['id'], DateHelper::now(),
    $data['temperature'], $data['pulse'],
    $data['blood_pressure_systolic'], $data['blood_pressure_diastolic'],
    $data['respiratory_rate'], $data['oxygen_saturation'],
    $data['height'], $data['weight'], $data['notes'] ?? '',
    DateHelper::now()
]);
Response::success(['id' => $pdo->lastInsertId()]);
