<?php
// api/modules/admin/ward/list.php
Auth::requireRole(['admin', 'doctor', 'nurse']);
$key = 'ward_list';
$cached = Cache::get($key);
if (is_array($cached)) {
    Response::success($cached);
}
$rows = DB::select("SELECT * FROM wards ORDER BY id DESC");
Cache::set($key, $rows, 300);
Response::success($rows);
