<?php
// api/modules/admin/integration_delete.php - 删除对接端点
Auth::requireRole(['admin']);
$id = Validator::get('id');
if (!$id) Response::error('缺少端点ID');
DB::execute("DELETE FROM integration_endpoints WHERE id = ?", [$id]);
Response::success();
