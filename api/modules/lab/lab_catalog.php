<?php
// api/modules/lab/lab_catalog.php - 检验项目库 (虚拟数据, 预留对接门诊项目库)
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$kw = trim((string)Validator::get('kw', ''));
$sql = "SELECT * FROM lab_catalog WHERE is_active = 1";
$params = [];
if ($kw !== '') {
    $sql .= " AND (name LIKE ? OR category LIKE ?)";
    $params[] = '%' . $kw . '%';
    $params[] = '%' . $kw . '%';
}
$sql .= " ORDER BY sort_no, id";
Response::success(DB::select($sql, $params));
