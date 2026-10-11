<?php
// api/modules/lab/lab_panel_delete.php - 删除常用检验套组模板 (本人或管理员)
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
$user = Auth::user();
$panel = DB::selectOne("SELECT * FROM lab_panels WHERE id = ?", [$id]);
if (!$panel) Response::error('套组不存在', 404);
if ($user['role'] !== 'admin' && (int)$panel['created_by'] !== (int)$user['id']) Response::error('只能删除本人创建的套组', 403);
DB::execute("DELETE FROM lab_panels WHERE id = ?", [$id]);
Response::success(null, '已删除');
