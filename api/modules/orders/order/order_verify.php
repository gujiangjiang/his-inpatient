<?php
// api/modules/orders/order_verify.php - 护士核对/确认 (开单核对, 确认作废, 确认停止, 执行/完成)
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');

$status = Validator::get('status', 'verified');

if (!$id) Response::error('请输入医嘱ID');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');
if (!can_access_patient(Auth::user(), $order['patient_id'])) Response::error('权限不足', 403);

    // 状态校验: 医嘱核对状态机
    $validTransition = [
        'draft' => ['pending'],                     // 草稿只能提交为 pending
        'pending' => ['verified'],                  // 待核对 -> 核对
        'invalid_pending' => ['invalid'],           // 待作废 -> 确认作废
        'stop_pending' => ['stopped'],              // 待停止 -> 确认停止
        'verified' => ['executing', 'completed'],
        'executing' => ['completed'],
        'completed' => [],
        'stopped' => [],
        'invalid' => []
    ];
if (!in_array($status, $validTransition[$order['status']] ?? [])) {
    Response::error('非法状态跳转：' . $order['status'] . ' → ' . $status);
}

$isRoot = ($order['parent_id'] === null || $order['is_group_main']);
$childIds = [];
if ($isRoot) {
    foreach (DB::select("SELECT id FROM orders WHERE parent_id = ?", [$id]) as $c) $childIds[] = (int)$c['id'];
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $userId = Auth::user()['id'];

    if ($status === 'invalid') {
        // 确认作废: 根 + 待作废子医嘱转 invalid, 关联发药记录作废
        $ids = array_merge([(int)$id], $childIds);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        DB::execute("UPDATE orders SET status = 'invalid', verified_by = ?, verified_at = ?, updated_at = ? WHERE id IN ($ph) AND status = 'invalid_pending'",
            array_merge([$userId, $now, $now], $ids));
        DB::execute("UPDATE dispensing_records SET status = 'invalid' WHERE order_id IN ($ph)", $ids);
    } elseif ($status === 'stopped') {
        // 确认停止: 根 + 待停止子医嘱转 stopped
        $stmt = $pdo->prepare("UPDATE orders SET status = 'stopped', verified_by = ?, verified_at = ?, updated_at = ?,
                stop_date = CASE WHEN stop_date IS NULL OR stop_date = '' THEN ? ELSE stop_date END
            WHERE id = ? AND status = 'stop_pending'");
        $stmt->execute([$userId, $now, $now, $now, $id]);
        $pdo->prepare("UPDATE orders SET status = 'stopped', verified_by = ?, verified_at = ?, updated_at = ?,
                stop_date = CASE WHEN stop_date IS NULL OR stop_date = '' THEN ? ELSE stop_date END
            WHERE parent_id = ? AND status = 'stop_pending'")
            ->execute([$userId, $now, $now, $now, $id]);
    } else {
        // 核对/执行/完成: 更新主医嘱并级联子医嘱
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, verified_by = ?, verified_at = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$status, $userId, $now, $now, $id]);
        if ($isRoot) {
            $pdo->prepare("UPDATE orders SET status = ?, verified_by = ?, verified_at = ?, updated_at = ? WHERE parent_id = ?")
                ->execute([$status, $userId, $now, $now, $id]);
        }
    }

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage());
}
