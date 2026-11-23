<?php
// api/modules/patients/patient_funds.php - 患者资金概览 (预交/已用/可透支/可用余额)
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
$user = Auth::user();
if (in_array($user['role'], ['doctor', 'nurse'], true) && !can_access_patient($user, $patientId)) {
    Response::error('无权查看该患者', 403);
}
Response::success(patient_funds($patientId));
