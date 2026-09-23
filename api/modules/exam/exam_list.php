<?php
// api/modules/exam/exam_list.php
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$patientId = Validator::get('patient_id');
$sql = "SELECT er.*, p.name as patient_name, p.bed_no, u.name as creator_name
        FROM exam_reports er
        LEFT JOIN patients p ON er.patient_id = p.id
        LEFT JOIN users u ON er.created_by = u.id";
$params = [];
if ($patientId) {
    $sql .= " WHERE er.patient_id = ?";
    $params[] = $patientId;
}
$sql .= " ORDER BY er.created_at DESC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
