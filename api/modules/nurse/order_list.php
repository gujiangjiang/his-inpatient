<?php
// api/modules/nurse/order_list.php - 护士站/核查清单医嘱 (待核查/已核对/执行中 + 发药标记)
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = Validator::get('patient_id');

// 待核查 (pending) 与已核对/执行 一并返回: 护士需要先看到待核查医嘱再核对
$sql = "SELECT o.*, u.name as doctor_name,
        (SELECT MAX(dr.id) FROM dispensing_records dr WHERE dr.order_id = o.id AND dr.status = 'dispensed') AS dispensed_id
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        WHERE o.status IN ('pending', 'verified', 'executing')";
$params = [];

if ($patientId) {
    $sql .= " AND o.patient_id = ?";
    $params[] = $patientId;
} elseif ($user['role'] === 'nurse') {
    // 护士只能看本病区患者的医嘱
    $sql .= " AND o.patient_id IN (SELECT id FROM patients WHERE ward_id = ?) ";
    $params[] = $user['ward_id'];
}

$sql .= " ORDER BY CASE o.status WHEN 'pending' THEN 0 WHEN 'verified' THEN 1 ELSE 2 END, o.id ASC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
