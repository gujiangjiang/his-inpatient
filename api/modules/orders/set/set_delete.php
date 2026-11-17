<?php
// api/modules/orders/set_delete.php - 删除医嘱组套 (连同条目)
Auth::requireRole(['admin', 'doctor']);
$id = Validator::get('id');
if (!$id) Response::error('缺少组套 ID');
$set = DB::selectOne("SELECT * FROM order_sets WHERE id = ?", [$id]);
if (!$set) Response::error('组套不存在');
$user = Auth::user();
/* 权限: 创建者本人或管理员可删 */
if ($user['role'] !== 'admin' && (int)$set['created_by'] !== (int)$user['id']) {
    Response::error('仅创建者或管理员可删除该组套');
}
DB::execute("DELETE FROM order_set_items WHERE set_id = ?", [$id]);
DB::execute("DELETE FROM order_sets WHERE id = ?", [$id]);
Response::success(['id' => (int)$id]);
