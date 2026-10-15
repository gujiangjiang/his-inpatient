<?php
// api/modules/orders/rx_void.php - 毁方 (整方作废, 护士未核对也可毁方)
Auth::requireRole(['admin', 'doctor']);
$rxNo = (string)Validator::get('rx_no', '');
$ids = Validator::get('ids');
if ($rxNo === '' && (!is_array($ids) || !count($ids))) Response::error('缺少处方号');

if ($rxNo !== '') {
    $rows = DB::select("SELECT id, patient_id, status FROM orders WHERE rx_no = ?", [$rxNo]);
} else {
    $ids = array_map('intval', $ids);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::select("SELECT id, patient_id, status FROM orders WHERE id IN ($ph) AND notes = '处方'", $ids);
}
if (!$rows) Response::error('处方不存在', 404);
if (!can_access_patient(Auth::user(), (int)$rows[0]['patient_id'])) Response::error('无权操作该患者', 403);

$voidIds = [];
foreach ($rows as $r) {
    if (in_array($r['status'], ['pending', 'verified', 'executing'])) $voidIds[] = (int)$r['id'];
}
if (!$voidIds) Response::error('该处方已作废或已完成，无需毁方');

$ph = implode(',', array_fill(0, count($voidIds), '?'));
$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    DB::execute("UPDATE orders SET status = 'invalid', updated_at = ? WHERE id IN ($ph)",
        array_merge([DateHelper::now()], $voidIds));
    // 已发药/摆药记录同步作废
    DB::execute("UPDATE dispensing_records SET status = 'invalid' WHERE order_id IN ($ph)", $voidIds);
    $pdo->commit();
    Response::success(['count' => count($voidIds)]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('毁方失败：' . $e->getMessage());
}
