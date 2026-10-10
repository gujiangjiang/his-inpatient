<?php
// api/modules/patients/by_department.php
// 按科室查询在院/出院患者 (校验医生科室授权)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$departmentId = Validator::get('department_id');
$status = Validator::get('status', 'active'); // active | discharged | all

if (!$departmentId) Response::error('缺少科室ID');

// 数据权限校验 (admin 或 授权科室, 含本人所属科室)
if (!can_access_department($user, $departmentId)) {
    Response::error('无权访问该科室', 403);
}

$sql = "SELECT p.*, d.name as department_name, w.name as ward_name
        FROM patients p
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        WHERE p.department_id = ?";
$params = [$departmentId];

if ($status !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY p.id DESC";

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));