<?php
// api/modules/patients/my_patients.php
// 当前登录医生主管的患者列表 (patient_assignments.attending_doctor_id)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();

$sql = "SELECT p.*, d.name as department_name, w.name as ward_name,
        a.name as attending_name, s.name as senior_name
        FROM patient_assignments pa
        JOIN patients p ON pa.patient_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN users a ON pa.attending_doctor_id = a.id
        LEFT JOIN users s ON pa.senior_doctor_id = s.id
        WHERE p.status = 'active'";
$params = [];
if ($user['role'] !== 'admin') {
    $sql .= " AND pa.attending_doctor_id = ?";
    $params[] = $user['id'];
}
$sql .= " ORDER BY p.id DESC";

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));