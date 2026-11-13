<?php
// api/modules/patients/patient_assign.php
// 指派医师 (三级医师): 住院医师(主管) + 主治医师(上级) + 主任医师 + 诊疗组, 拉入当前科室
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$patientId = Validator::get('patient_id');
$attendingDoctorId = Validator::get('attending_doctor_id');
$seniorDoctorId = Validator::get('senior_doctor_id');
$directorDoctorId = Validator::get('director_doctor_id');
$treatmentGroup = trim((string)Validator::get('treatment_group', ''));

if (!$patientId) Response::error('缺少患者ID');
if (!$attendingDoctorId) Response::error('请指定住院医师（主管医师）');

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');
// 医生仅可指派自己授权科室范围内的患者
if (!can_access_patient(Auth::user(), $patientId)) Response::error('权限不足', 403);

$existing = DB::selectOne("SELECT * FROM patient_assignments WHERE patient_id = ?", [$patientId]);

$pdo->beginTransaction();
try {
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE patient_assignments SET attending_doctor_id=?, senior_doctor_id=?, director_doctor_id=?, treatment_group=?, department_id=?, created_at=? WHERE patient_id=?");
        $stmt->execute([
            $attendingDoctorId, $seniorDoctorId ?: null, $directorDoctorId ?: null, $treatmentGroup,
            $patient['department_id'], DateHelper::now(), $patientId,
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO patient_assignments (patient_id, admission_no, attending_doctor_id, senior_doctor_id, director_doctor_id, treatment_group, department_id, created_at) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $patientId, $patient['admission_no'], $attendingDoctorId, $seniorDoctorId ?: null,
            $directorDoctorId ?: null, $treatmentGroup, $patient['department_id'], DateHelper::now(),
        ]);
    }
    $pdo->commit();
    Response::success(['patient_id' => $patientId]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('指派失败: ' . $e->getMessage());
}
