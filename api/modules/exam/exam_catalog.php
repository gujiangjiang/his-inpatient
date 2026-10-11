<?php
// api/modules/exam/exam_catalog.php - 检查项目库 (虚拟数据, 预留对接门诊检查项目库)
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$kw = trim((string)Validator::get('kw', ''));
$category = trim((string)Validator::get('category', ''));
$sql = "SELECT * FROM exam_catalog WHERE is_active = 1";
$params = [];
if ($category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
}
if ($kw !== '') {
    $sql .= " AND (name LIKE ? OR sub_category LIKE ?)";
    $params[] = '%' . $kw . '%';
    $params[] = '%' . $kw . '%';
}
$sql .= " ORDER BY sort_no, id";
Response::success(DB::select($sql, $params));
