<?php
// api/modules/orders/order_invalidate.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) Response::error('请输入医嘱ID');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');

// 作废条件：必须已被护士核对 (verified 状态) 才能作废
// OR 已提交(pending)但护士已核对
if (!in_array($order['status'], ['verified', 'executing'])) {
    Response::error('只有护士核对后的医嘱才可作废');
}

// 不能作废已完成的医嘱
if ($order['status'] === 'completed') {
    Response::error('已完成的医嘱不能作废');
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $stmt = $pdo->prepare("UPDATE orders SET status = 'invalid', updated_at = ? WHERE id = ?");
    $stmt->execute([$now, $id]);

    // 级联子医嘱
    if ($order['parent_id'] === null || $order['is_group_main']) {
        $pdo->prepare("UPDATE orders SET status = 'invalid', updated_at = ? WHERE parent_id = ?")
            ->execute([$now, $id]);
    }

    // 如果有关联发药记录，标记为已作废
    DB::execute("UPDATE dispensing_records SET status = 'invalid' WHERE order_id = ?", [$id]);

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('作废失败：' . $e->getMessage());
}
