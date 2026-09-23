<?php
// api/modules/nurse/vitals_list.php
Auth::requireRole(['admin', 'nurse']);
$patientId = Validator::get('patient_id');
if (!$patientId) Response::error('请输入患者ID');
$stmt = DB::getPDO()->prepare("SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY created_at DESC");
$stmt->execute([$patientId]);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
