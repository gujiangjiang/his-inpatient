<?php
// api/modules/emr/diagnosis_template_list.php - 常用诊断模板列表 (本人)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$sql = "SELECT * FROM diagnosis_templates";
$params = [];
if ($user['role'] !== 'admin') {
    $sql .= " WHERE created_by = ?";
    $params[] = $user['id'];
}
$sql .= " ORDER BY id DESC LIMIT 100";
Response::success(DB::select($sql, $params));
