<?php
// api/modules/lab/lab_list.php
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$patientId = Validator::get('patient_id');
$sql = "SELECT lr.*, p.name as patient_name, p.bed_no, u.name as creator_name
        FROM lab_reports lr
        LEFT JOIN patients p ON lr.patient_id = p.id
        LEFT JOIN users u ON lr.created_by = u.id";
$params = [];
if ($patientId) {
    $sql .= " WHERE lr.patient_id = ?";
    $params[] = $patientId;
}
$sql .= " ORDER BY lr.created_at DESC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
