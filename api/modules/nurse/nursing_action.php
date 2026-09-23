<?php
// api/modules/nurse/nursing_action.php
Auth::requireRole(['admin', 'nurse']);
$data = Validator::all();
$orderId = Validator::get('order_id');
$action = Validator::get('action');
$notes = Validator::get('notes', '');

if (!$orderId || !$action) Response::error('请选择医嘱和执行动作');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
if (!$order) Response::error('医嘱不存在');

$pdo->beginTransaction();
try {
    // 执行留痕
    $stmt = $pdo->prepare("INSERT INTO nursing_records (order_id, patient_id, action, executed_by, executed_at, notes, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $orderId, $order['patient_id'], $action, Auth::user()['id'], DateHelper::now(), $notes, DateHelper::now()
    ]);

    // 更新医嘱状态
    DB::execute("UPDATE orders SET status = ?, updated_at = ? WHERE id = ?", ['executing', DateHelper::now(), $orderId]);

    // 如果是药品类，标记为已发药
    if ($order['category'] === 'medication' && $action === 'execute') {
        // 检查是否有发药记录
        $disp = DB::selectOne("SELECT * FROM dispensing_records WHERE order_id = ? AND status = 'dispensed'", [$orderId]);
        if (!$disp) {
            Response::error('药品未发药，无法执行', 400);
        }
        DB::execute("UPDATE dispensing_records SET status = 'delivered' WHERE order_id = ?", [$orderId]);
    }

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage());
}
