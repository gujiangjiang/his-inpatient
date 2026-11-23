<?php
// api/modules/orders/order_stop.php - 停止医嘱申请 (单个/长期全停, 待护士确认后转为 stopped)
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
    if (!in_array($order['status'], $stoppable)) Response::error('当前状态不可停止');
    // 仅长期医嘱可停止
    if ($order['order_period'] !== 'long') Response::error('仅长期医嘱可停止, 临时医嘱可作废或删除');
    // 停止时间不能早于开具时间（start_date 为本地墙钟业务时间，两模式一致比较）
    if (!empty($order['start_date']) && strtotime(TimeHelper::nowLocal()) < strtotime($order['start_date'])) {
        Response::error('停止时间不能早于开具时间');
    }
} else {
    if (!can_access_patient(Auth::user(), $patientId)) Response::error('权限不足', 403);
}

$pdo->beginTransaction();
try {
    if ($id) {
        DB::execute("UPDATE orders SET status = 'stop_pending', stop_date = ?, stopped_by = ?, updated_at = ? WHERE id = ? AND status IN (?,?,?)",
            [$now, $userId, $now, $id, 'pending', 'verified', 'executing']);
        // 组内子医嘱级联
        DB::execute("UPDATE orders SET status = 'stop_pending', stop_date = ?, stopped_by = ?, updated_at = ? WHERE parent_id = ? AND status IN (?,?,?)",
            [$now, $userId, $now, $id, 'pending', 'verified', 'executing']);
        $count = 1;
    } else {
        // 长期全停: 该患者所有长期医嘱 (含子医嘱), 且开具时间不晚于当前时间
        $stmt = $pdo->prepare("UPDATE orders SET status = 'stop_pending', stop_date = ?, stopped_by = ?, updated_at = ?
                               WHERE patient_id = ? AND order_period = 'long' AND status IN ('pending','verified','executing')
                                 AND (start_date IS NULL OR start_date = '' OR start_date <= ?)");
        $stmt->execute([$now, $userId, $now, $patientId, $now]);
        $count = $stmt->rowCount();
    }
    $pdo->commit();
    Response::success(['stopping' => $count]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('停止失败：' . $e->getMessage());
}
