<?php
// api/modules/orders/order_submit.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$patientId = Validator::get('patient_id');
$ids = Validator::get('ids'); // 可选: 提交指定 ID 的医嘱

if (!$patientId) Response::error('请选择患者');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('权限不足', 403);

$pdo = DB::getPDO();
$now = DateHelper::now();
$rxParams = [$patientId];
$rxWhere = "patient_id = ? AND status = 'draft' AND notes = '处方' AND rx_no IS NOT NULL AND rx_no <> ''";
if ($ids && is_array($ids)) {
    $rxWhere .= " AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
    $rxParams = array_merge($rxParams, $ids);
}
$rxRows = DB::select("SELECT DISTINCT rx_no FROM orders WHERE $rxWhere", $rxParams);

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
    foreach ($rxRows as $rxRow) {
        $rxNo = (string)$rxRow['rx_no'];
        $snapshot = print_snapshot_rx_payload($rxNo);
        print_snapshot_capture('prescription', $rxNo, $snapshot, $patientId, '', Auth::user()['id']);
    }
    $pdo->commit();
    /* 推送: 病区护士「新医嘱待核对」通知 (按患者重建; 处理完自动消失) */
    try { notify_orders_sync((int)$patientId); } catch (Exception $e) { /* 推送失败不影响提交 */ }
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('提交失败：' . $e->getMessage());
}
