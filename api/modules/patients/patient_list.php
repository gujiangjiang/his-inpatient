<?php
// api/modules/patients/patient_list.php - 患者列表 (卡片墙数据源)
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$user = Auth::user();
$pdo = DB::getPDO();

$status = Validator::get('status', 'active');
$departmentId = Validator::get('department_id');

$sql = "SELECT p.*, d.name AS department_name, d.risk_assessment, w.name AS ward_name,
               a.name AS attending_name, s.name AS senior_name
        FROM patients p
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN patient_assignments pa ON pa.patient_id = p.id
        LEFT JOIN users a ON pa.attending_doctor_id = a.id
        LEFT JOIN users s ON pa.senior_doctor_id = s.id
        WHERE p.status = ?
          AND EXISTS (SELECT 1 FROM patient_assignments pax WHERE pax.patient_id = p.id AND pax.attending_doctor_id IS NOT NULL)";
$params = [$status];

if ($departmentId) {
    // 数据权限: 医生仅可查询授权范围内科室, 越权 403
    if ($user['role'] === 'doctor' && !can_access_department($user, $departmentId)) {
        Response::error('无权访问该科室', 403);
    }
    $sql .= " AND p.department_id = ?";
    $params[] = $departmentId;
}

// 护士按病区过滤；医生严格按授权科室范围过滤 (无授权则空集)
if ($user['role'] === 'nurse') {
    $sql .= " AND p.ward_id = ?";
    $params[] = $user['ward_id'];
} elseif ($user['role'] === 'doctor') {
    $allowed = allowed_department_ids($user);
    if ($allowed) {
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $sql .= " AND p.department_id IN ({$placeholders})";
        $params = array_merge($params, $allowed);
    } else {
        $sql .= " AND 1 = 0";
    }
}

$sql .= " ORDER BY p.ward_id ASC, p.bed_no ASC, p.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
apply_latest_nursing_level($rows);
Response::success($rows);
