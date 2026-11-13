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

// 状态机校验 (与 order_verify 一致: 开单/停止/作废均需护士确认)
$validTransition = [
    'draft' => ['pending'],
    'pending' => ['verified', 'draft', 'invalid_pending'],
    'invalid_pending' => ['invalid'],
    'stop_pending' => ['stopped'],
    'verified' => ['executing', 'completed', 'invalid_pending', 'stop_pending'],
    'executing' => ['completed', 'invalid_pending', 'stop_pending'],
    'completed' => [],
    'stopped' => [],
    'invalid' => []
];
if (!in_array($status, $validTransition[$order['status']] ?? [])) {
    Response::error('非法状态跳转：' . $order['status'] . ' → ' . $status);
}

// 停止校验: 仅长期医嘱, 且停止时间不能早于开具时间
if ($status === 'stopped') {
    if ($order['order_period'] !== 'long') Response::error('仅长期医嘱可停止, 临时医嘱可作废或删除');
    // start_date 为本地墙钟业务时间，用本地当前墙钟一致比较（不随存储模式变化）
    $nowCheck = TimeHelper::nowLocal();
    if (!empty($order['start_date']) && strtotime($nowCheck) < strtotime($order['start_date'])) {
        Response::error('停止时间不能早于开具时间');
    }
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    if ($status === 'stopped') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, stop_date = ?, stopped_by = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$status, $now, Auth::user()['id'], $now, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$status, $now, $id]);
    }

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
