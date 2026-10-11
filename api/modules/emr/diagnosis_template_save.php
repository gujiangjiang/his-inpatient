<?php
// api/modules/emr/diagnosis_template_save.php - 保存常用诊断模板
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$name = trim((string)Validator::get('name', ''));
$icdCode = trim((string)Validator::get('icd_code', ''));
$category = (string)Validator::get('category', '');
if ($name === '') Response::error('诊断名称不能为空');

$dup = DB::selectOne(
    "SELECT id FROM diagnosis_templates WHERE created_by = ? AND name = ? AND IFNULL(icd_code,'') = ?",
    [$user['id'], $name, $icdCode]
);
if ($dup) Response::error('该常用诊断已存在');

DB::insert('diagnosis_templates', [
    'name' => $name,
    'icd_code' => $icdCode,
    'category' => $category,
    'created_by' => $user['id'],
    'dept_id' => $user['department_id'],
    'created_at' => DateHelper::now(),
]);
Response::success(null, '已存为常用诊断');
