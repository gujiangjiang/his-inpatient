<?php
// api/modules/orders/order_batch_delete.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();
$ids = Validator::get('ids');

if (!$ids || !is_array($ids)) Response::error('请选择要删除的医嘱');

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    foreach ($ids as $id) {
        $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
        if (!$order) continue;
        // 只能删除草稿状态
        if ($order['status'] !== 'draft') {
            Response::error('只能删除草稿状态的医嘱');
        }
        // 删除子医嘱
        DB::delete('orders', 'parent_id = ?', [$id]);
        DB::delete('orders', 'id = ?', [$id]);
    }
    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage());
}
