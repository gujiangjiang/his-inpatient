<?php
// api/modules/patients/bed_list.php
Auth::requireRole(['admin', 'doctor', 'nurse']);
$pdo = DB::getPDO();

$wardId = Validator::get('ward_id');
$sql = "SELECT b.*, w.name as ward_name, p.name as patient_name 
        FROM beds b 
        LEFT JOIN wards w ON b.ward_id = w.id 
        LEFT JOIN patients p ON b.patient_id = p.id";
$params = [];
if ($wardId) {
    $sql .= " WHERE b.ward_id = ?";
    $params[] = $wardId;
}
$sql .= " ORDER BY w.id, b.bed_no";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
