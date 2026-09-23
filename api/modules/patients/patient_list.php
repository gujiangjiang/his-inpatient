<?php
// api/modules/patients/patient_list.php
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$user = Auth::user();
$pdo = DB::getPDO();

$sql = "SELECT p.*, d.name as department_name, w.name as ward_name 
        FROM patients p 
        LEFT JOIN departments d ON p.department_id = d.id 
        LEFT JOIN wards w ON p.ward_id = w.id 
        WHERE p.status = 'active'";
$params = [];

// 护士按病区过滤，医生按科室过滤
if ($user['role'] === 'nurse') {
    $sql .= " AND p.ward_id = ?";
    $params[] = $user['ward_id'];
} elseif ($user['role'] === 'doctor') {
    $sql .= " AND p.department_id = ?";
    $params[] = $user['department_id'];
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
