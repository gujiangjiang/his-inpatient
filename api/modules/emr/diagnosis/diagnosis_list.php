<?php
// api/modules/emr/diagnosis_list.php - 患者诊断列表 (按分类)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);
$rows = DB::select(
    "SELECT * FROM patient_diagnoses WHERE patient_id = ? ORDER BY category, sort_no, id",
    [$patientId]
);
Response::success($rows);
