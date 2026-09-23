<?php
// api/modules/orders/order_verify.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');

$status = Validator::get('status', 'verified');

if (!$id) Response::error('请输入医嘱ID');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');

// 状态校验
$validTransition = [
    'draft' => ['pending', 'verified'],
    'pending' => ['verified'],
    'verified' => ['executing', 'completed'],
    'executing' => ['completed'],
    'completed' => []
];
if (!in_array($status, $validTransition[$order['status']] ?? [])) {
    Response::error('非法状态跳转：' . $order['status'] . ' → ' . $status);
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    // 更新主医嘱
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, verified_by = ?, verified_at = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$status, Auth::user()['id'], $now, $now, $id]);

    // 级联更新子医嘱
    if ($order['is_group_main'] || $order['parent_id'] === null) {
        $pdo->prepare("UPDATE orders SET status = ?, verified_by = ?, verified_at = ?, updated_at = ? WHERE parent_id = ?")
            ->execute([$status, Auth::user()['id'], $now, $now, $id]);
    }

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage());
}
