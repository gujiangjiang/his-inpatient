<?php
// api/modules/emr/dept_doctors.php - 科室医生列表 (上级医师查房记录选择上级医师用)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$deptId = (int)Validator::get('department_id', 0);
if (!$deptId) $deptId = (int)(Auth::user()['department_id'] ?? 0);
if (!$deptId) Response::success([]);
$rows = DB::select(
    "SELECT id, name FROM users WHERE role = 'doctor' AND is_active = 1 AND department_id = ? ORDER BY name",
    [$deptId]
);
Response::success($rows);
