<?php
// api/modules/emr/content_delete.php - 内容库条目删除
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
if (!$id) Response::error('缺少 id');
$row = DB::selectOne("SELECT id FROM emr_contents WHERE id = ?", [$id]);
if (!$row) Response::error('内容不存在', 404);
DB::delete('emr_contents', 'id = ?', [$id]);
Response::success(['id' => $id]);
