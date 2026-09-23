<?php
// api/modules/orders/order_status.php
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');
$status = Validator::get('status');

if (!$id || !$status) Response::error('请输入医嘱ID和状态');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');

// 状态机校验
$validTransition = [
    'draft' => ['pending'],
    'pending' => ['verified', 'draft'],
    'verified' => ['executing', 'completed', 'invalid'],
    'executing' => ['completed'],
    'completed' => [],
    'invalid' => []
];
if (!in_array($status, $validTransition[$order['status']] ?? [])) {
    Response::error('非法状态跳转：' . $order['status'] . ' → ' . $status);
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$status, $now, $id]);

    // 级联子医嘱
    if ($order['parent_id'] === null || $order['is_group_main']) {
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE parent_id = ?")
            ->execute([$status, $now, $id]);
    }

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage());
}
