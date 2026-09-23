<?php
// api/modules/nurse/order_list.php
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = Validator::get('patient_id');

$sql = "SELECT o.*, u.name as doctor_name FROM orders o 
        LEFT JOIN users u ON o.created_by = u.id 
        WHERE o.status IN ('verified', 'executing')";
$params = [];

if ($patientId) {
    $sql .= " AND o.patient_id = ?";
    $params[] = $patientId;
} elseif ($user['role'] === 'nurse') {
    // 护士只能看本病区患者的医嘱
    $sql .= " AND o.patient_id IN (SELECT id FROM patients WHERE ward_id = ?) ";
    $params[] = $user['ward_id'];
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
