<?php
// api/modules/doctors/doctor_list.php
// 医生列表 (按科室过滤, 供指派医师弹窗使用)
Auth::requireRole(['admin', 'doctor']);
$departmentId = Validator::get('department_id');

$sql = "SELECT id, username, name, department_id, ward_id, role
        FROM users WHERE role IN ('doctor','admin') AND is_active = 1";
$params = [];
if ($departmentId) {
    $sql .= " AND department_id = ?";
    $params[] = $departmentId;
}
$sql .= " ORDER BY id";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));