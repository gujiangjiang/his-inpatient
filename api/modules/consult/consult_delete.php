<?php
// api/modules/consult/consult_delete.php - 删除会诊申请 (仅发起人 + 未回复, 同步删除关联医嘱)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$id = (int)Validator::get('id', 0);
$consult = DB::selectOne("SELECT * FROM consultations WHERE id = ?", [$id]);
if (!$consult) Response::error('会诊不存在', 404);
if ($user['role'] !== 'admin' && (int)$consult['created_by'] !== (int)$user['id']) Response::error('只能删除本人发起的会诊', 403);
if ($consult['status'] !== 'pending') Response::error('已回复的会诊不能删除');

if ($consult['order_id']) {
    $o = DB::selectOne("SELECT id, status FROM orders WHERE id = ?", [$consult['order_id']]);
    if ($o && !in_array($o['status'], ['draft', 'pending'], true)) Response::error('关联医嘱已执行，不能删除会诊');
}

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    if ($consult['order_id']) {
        DB::execute("DELETE FROM orders WHERE id = ? AND status IN ('draft', 'pending')", [$consult['order_id']]);
    }
    DB::execute("DELETE FROM consultations WHERE id = ?", [$id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('删除失败: ' . $e->getMessage(), 500);
}
Response::success(null, '已删除');
