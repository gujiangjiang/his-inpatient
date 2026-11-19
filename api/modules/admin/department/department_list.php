<?php
// api/modules/admin/department/department_list.php
Auth::requireRole(['admin', 'doctor', 'nurse']);
$key = 'dept_list';
$cached = Cache::get($key);
if (is_array($cached)) {
    Response::success($cached);
}
$rows = DB::select("SELECT * FROM departments ORDER BY id DESC");
Cache::set($key, $rows, 300);
Response::success($rows);
