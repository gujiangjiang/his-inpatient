<?php
// api/modules/patients/patient_list.php - 患者列表 (卡片墙数据源)
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$user = Auth::user();
$pdo = DB::getPDO();

$status = Validator::get('status', 'active');
$departmentId = Validator::get('department_id');

$sql = "SELECT p.*, d.name AS department_name, w.name AS ward_name,
               a.name AS attending_name, s.name AS senior_name
        FROM patients p
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN patient_assignments pa ON pa.patient_id = p.id
        LEFT JOIN users a ON pa.attending_doctor_id = a.id
        LEFT JOIN users s ON pa.senior_doctor_id = s.id
        WHERE p.status = ?";
$params = [$status];

if ($departmentId) {
    // 数据权限: 医生仅可查询授权范围内科室, 越权 403
    if ($user['role'] === 'doctor' && !can_access_department($user, $departmentId)) {
        Response::error('无权访问该科室', 403);
    }
    $sql .= " AND p.department_id = ?";
    $params[] = $departmentId;
}

// 护士按病区过滤，医生按授权科室或指派管辖过滤
if ($user['role'] === 'nurse') {
    $sql .= " AND p.ward_id = ?";
    $params[] = $user['ward_id'];
} elseif ($user['role'] === 'doctor') {
    $allowed = allowed_department_ids($user);
    if ($allowed) {
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $sql .= " AND (p.department_id IN ({$placeholders}) OR pa.attending_doctor_id = ? OR pa.senior_doctor_id = ?)";
        $params = array_merge($params, $allowed, [$user['id'], $user['id']]);
    } else {
        $sql .= " AND (pa.attending_doctor_id = ? OR pa.senior_doctor_id = ?)";
        $params = array_merge($params, [$user['id'], $user['id']]);
    }
}

$sql .= " ORDER BY p.ward_id ASC, p.bed_no ASC, p.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
