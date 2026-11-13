<?php
// 内容审核 (管理员): approve / reject(附原因)
Auth::requireRole(['admin']);
$id = (int)Validator::get('id', 0);
$action = Validator::get('action');
if (!$id || !in_array($action, ['approve', 'reject'], true)) Response::error('参数不完整');

$row = DB::selectOne("SELECT id, pub_status FROM emr_contents WHERE id = ?", [$id]);
if (!$row) Response::error('内容不存在', 404);
if (($row['pub_status'] ?? '') !== 'pending') Response::error('仅待审核项可操作', 400);

$reason = trim((string)Validator::get('reason', ''));
$pdo = DB::getPDO();
$uid = Auth::user()['id'];
$now = DateHelper::now();
if ($action === 'approve') {
    $pdo->prepare("UPDATE emr_contents SET pub_status='approved', pub_audited_by=?, pub_audited_at=?, pub_reject_reason=NULL WHERE id=?")
        ->execute([$uid, $now, $id]);
} else {
    $pdo->prepare("UPDATE emr_contents SET pub_status='rejected', pub_audited_by=?, pub_audited_at=?, pub_reject_reason=? WHERE id=?")
        ->execute([$uid, $now, $reason, $id]);
}
Response::success(['id' => $id]);