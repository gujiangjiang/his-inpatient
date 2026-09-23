<?php
// api/modules/doctors/doctor_departments.php
// 当前登录医生有权限管辖的科室列表
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();

// admin 返回全部科室
if ($user['role'] === 'admin') {
    $departments = DB::select("SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY id");
    Response::success(['departments' => $departments]);
}

$departments = DB::select("SELECT d.id, d.code, d.name
    FROM doctor_department_permissions p
    JOIN departments d ON p.department_id = d.id
    WHERE p.doctor_id = ? AND d.is_active = 1
    ORDER BY d.id", [$user['id']]);

Response::success(['departments' => $departments]);