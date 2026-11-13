<?php
// api/modules/emr/diagnosis_template_delete.php - 删除常用诊断模板 (本人或管理员)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$id = (int)Validator::get('id', 0);
$row = DB::selectOne("SELECT * FROM diagnosis_templates WHERE id = ?", [$id]);
if (!$row) Response::error('模板不存在', 404);
if ($user['role'] !== 'admin' && (int)$row['created_by'] !== (int)$user['id']) Response::error('只能删除本人创建的模板', 403);
DB::execute("DELETE FROM diagnosis_templates WHERE id = ?", [$id]);
Response::success(null, '已删除');
