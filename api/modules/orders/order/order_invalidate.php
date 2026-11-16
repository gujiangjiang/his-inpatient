<?php
// api/modules/orders/order_invalidate.php - 作废医嘱 (提交作废申请, 待护士确认后转为 invalid)
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) Response::error('请输入医嘱ID');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');
if (!can_access_patient(Auth::user(), $order['patient_id'])) Response::error('权限不足', 403);

// 作废申请条件: 待核对/已核对/执行中 (护士确认后正式作废; 待作废/已作废不可重复申请)
if (!in_array($order['status'], ['pending', 'verified', 'executing'])) {
    Response::error('当前状态不可作废');
}

/* 手术医嘱不可直接作废: 需在「手术」页面取消手术申请 (取消后自动作废) */
$checkIds = [(int)$id];
if (!empty($order['parent_id'])) $checkIds[] = (int)$order['parent_id'];
$phS = implode(',', array_fill(0, count($checkIds), '?'));
$isSurgeryOrder = (($order['category'] ?? '') === 'surgery') || ((int)(DB::selectOne(
    "SELECT COUNT(*) AS n FROM surgeries WHERE order_id IN ($phS)", $checkIds)['n'] ?? 0) > 0);
if ($isSurgeryOrder) {
    Response::error('手术医嘱不可直接作废：请在「手术」页面「已申请手术」列表中取消手术（取消后该医嘱自动作废）');
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $stmt = $pdo->prepare("UPDATE orders SET status = 'invalid_pending', updated_at = ? WHERE id = ? AND status IN ('pending','verified','executing')");
    $stmt->execute([$now, $id]);

    // 级联子医嘱 (同组一起申请作废)
    if ($order['parent_id'] === null || $order['is_group_main']) {
        $pdo->prepare("UPDATE orders SET status = 'invalid_pending', updated_at = ? WHERE parent_id = ? AND status IN ('pending','verified','executing')")
            ->execute([$now, $id]);
    }

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('作废失败：' . $e->getMessage());
}
