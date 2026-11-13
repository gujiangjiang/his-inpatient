<?php
// api/modules/exam/exam_delete.php - 删除检查申请 (仅未执行; 关联医嘱未核对则标记作废, 已核对需先处理医嘱)
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
$user = Auth::user();
$report = DB::selectOne("SELECT * FROM exam_reports WHERE id = ?", [$id]);
if (!$report) Response::error('检查单不存在', 404);
if (!can_access_patient($user, $report['patient_id'])) Response::error('无权操作该患者', 403);
if ($report['status'] !== 'pending') Response::error('已执行/已出报告，不能删除');

$orderIds = json_decode((string)($report['order_ids'] ?? '[]'), true) ?: [];
foreach ($orderIds as $oid) {
    $o = DB::selectOne("SELECT id, status FROM orders WHERE id = ?", [$oid]);
    if ($o && !in_array($o['status'], ['draft', 'pending'], true)) {
        Response::error('关联医嘱已核对/执行，不能删除（请先在医嘱列表处理）');
    }
}

$pdo = DB::getPDO();
$now = DateHelper::now();
$pdo->beginTransaction();
try {
    // 医嘱保留痕迹: 未核对 (草稿/待核对) 的关联医嘱标记「作废」
    foreach ($orderIds as $oid) {
        DB::execute(
            "UPDATE orders SET status = 'invalid', updated_at = ? WHERE id = ? AND status IN ('draft', 'pending')",
            [$now, $oid]
        );
    }
    DB::execute("DELETE FROM exam_reports WHERE id = ?", [$id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('删除失败: ' . $e->getMessage(), 500);
}
Response::success(null, '已删除，关联医嘱已标记作废');
