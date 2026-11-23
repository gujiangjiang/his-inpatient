<?php
// api/modules/patients/assignment_get.php
// 查询患者当前医师指派 (三级医师)
Auth::requireRole(['admin', 'doctor']);
$patientId = Validator::get('patient_id');
if (!$patientId) Response::error('缺少患者ID');

$assignment = DB::selectOne("SELECT pa.*, a.name as attending_name, s.name as senior_name, d.name as director_name
    FROM patient_assignments pa
    LEFT JOIN users a ON pa.attending_doctor_id = a.id
    LEFT JOIN users s ON pa.senior_doctor_id = s.id
    LEFT JOIN users d ON pa.director_doctor_id = d.id
    WHERE pa.patient_id = ?", [$patientId]);

Response::success($assignment);
