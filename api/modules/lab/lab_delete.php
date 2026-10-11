<?php
// api/modules/lab/lab_delete.php - 删除检验申请 (仅未执行, 同步删除关联草稿医嘱)
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
$user = Auth::user();
$report = DB::selectOne("SELECT * FROM lab_reports WHERE id = ?", [$id]);
if (!$report) Response::error('检验单不存在', 404);
if (!can_access_patient($user, $report['patient_id'])) Response::error('无权操作该患者', 403);
if ($report['status'] !== 'pending') Response::error('已采集/已出报告，不能删除');

$orderIds = json_decode((string)($report['order_ids'] ?? '[]'), true) ?: [];
foreach ($orderIds as $oid) {
    $o = DB::selectOne("SELECT id, status FROM orders WHERE id = ?", [$oid]);
    if ($o && $o['status'] !== 'draft') Response::error('关联医嘱已提交，不能删除');
}

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    foreach ($orderIds as $oid) {
        DB::execute("DELETE FROM orders WHERE id = ? AND status = 'draft'", [$oid]);
    }
    DB::execute("DELETE FROM lab_report_items WHERE report_id = ?", [$id]);
    DB::execute("DELETE FROM lab_reports WHERE id = ?", [$id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('删除失败: ' . $e->getMessage(), 500);
}
Response::success(null, '已删除');
