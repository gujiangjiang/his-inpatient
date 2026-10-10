<?php
// api/modules/orders/order_stop.php - 停止医嘱 (单个停止 / 长期全停)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$id = Validator::get('id');
$patientId = Validator::get('patient_id');

if (!$id && !$patientId) Response::error('请指定医嘱或患者');

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$now = DateHelper::now();
$stoppable = ['pending', 'verified', 'executing'];

if ($id) {
    $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
    if (!$order) Response::error('医嘱不存在');
    if (!can_access_patient(Auth::user(), $order['patient_id'])) Response::error('权限不足', 403);
} else {
    if (!can_access_patient(Auth::user(), $patientId)) Response::error('权限不足', 403);
}

$pdo->beginTransaction();
try {
    if ($id) {
        $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
        if (!$order) Response::error('医嘱不存在');
        if (!in_array($order['status'], $stoppable)) Response::error('当前状态不可停止');
        DB::execute("UPDATE orders SET status = 'stopped', stop_date = ?, stopped_by = ?, updated_at = ? WHERE id = ? AND status IN (?,?,?)",
            [$now, $userId, $now, $id, 'pending', 'verified', 'executing']);
        DB::execute("UPDATE orders SET status = 'stopped', stop_date = ?, stopped_by = ?, updated_at = ? WHERE parent_id = ? AND status IN (?,?,?)",
            [$now, $userId, $now, $id, 'pending', 'verified', 'executing']);
        $count = 1;
    } else {
        // 长期全停: 该患者所有长期医嘱 (含子医嘱)
        $stmt = $pdo->prepare("UPDATE orders SET status = 'stopped', stop_date = ?, stopped_by = ?, updated_at = ?
                               WHERE patient_id = ? AND order_period = 'long' AND status IN ('pending','verified','executing')");
        $stmt->execute([$now, $userId, $now, $patientId]);
        $count = $stmt->rowCount();
    }
    $pdo->commit();
    Response::success(['stopped' => $count]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('停止失败：' . $e->getMessage());
}
