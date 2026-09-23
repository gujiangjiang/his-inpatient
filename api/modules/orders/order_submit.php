<?php
// api/modules/orders/order_submit.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$patientId = Validator::get('patient_id');
$ids = Validator::get('ids'); // 可选: 提交指定 ID 的医嘱

if (!$patientId) Response::error('请选择患者');

$pdo = DB::getPDO();
$now = DateHelper::now();

$pdo->beginTransaction();
try {
    if ($ids && is_array($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE orders SET status = 'pending', updated_at = ? WHERE id IN ({$placeholders}) AND status = 'draft' AND patient_id = ?");
        $stmt->execute(array_merge([$now], $ids, [$patientId]));
    } else {
        // 提交所有草稿
        $stmt = $pdo->prepare("UPDATE orders SET status = 'pending', updated_at = ? WHERE patient_id = ? AND status = 'draft'");
        $stmt->execute([$now, $patientId]);
    }
    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('提交失败：' . $e->getMessage());
}
