<?php
// api/modules/emr/list.php
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$patientId = Validator::get('patient_id');
$recordType = Validator::get('record_type');

if (!$patientId) {
    Response::error('请输入患者ID');
}

$sql = "SELECT * FROM emr_records WHERE patient_id = ?";
$params = [$patientId];

if ($recordType) {
    $sql .= " AND record_type = ?";
    $params[] = $recordType;
}

$sql .= " ORDER BY created_at DESC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
