<?php
// 模板审核 (管理员): approve / reject(附原因)
Auth::requireRole(['admin']);
$id = (int)Validator::get('id', 0);
$action = Validator::get('action');
if (!$id || !in_array($action, ['approve', 'reject'], true)) Response::error('参数不完整');

$row = DB::selectOne("SELECT id, pub_status FROM emr_templates WHERE id = ?", [$id]);
if (!$row) Response::error('模板不存在', 404);
if (($row['pub_status'] ?? '') !== 'pending') Response::error('仅待审核项可操作', 400);

$reason = trim((string)Validator::get('reason', ''));
$pdo = DB::getPDO();
$uid = Auth::user()['id'];
$now = DateHelper::now();
if ($action === 'approve') {
    $pdo->prepare("UPDATE emr_templates SET pub_status='approved', pub_audited_by=?, pub_audited_at=?, pub_reject_reason=NULL WHERE id=?")
        ->execute([$uid, $now, $id]);
} else {
    $pdo->prepare("UPDATE emr_templates SET pub_status='rejected', pub_audited_by=?, pub_audited_at=?, pub_reject_reason=? WHERE id=?")
        ->execute([$uid, $now, $reason, $id]);
}
/* 推送: 管理员待办消除 (已审核) + 通知申请人结果 */
notify_resolve('template', $id);
$tpl = DB::selectOne("SELECT name, created_by FROM emr_templates WHERE id = ?", [$id]);
if ($tpl && (int)$tpl['created_by'] !== (int)$uid) {
    notify_send([
        'user_ids' => [(int)$tpl['created_by']],
        'type' => 'template',
        'title' => '模板' . ($action === 'approve' ? '审核通过' : '被驳回') . '：' . $tpl['name'],
        'body' => $action === 'approve' ? '已按发布范围生效，可在模板管理中查看' : ('原因：' . ($reason !== '' ? $reason : '未注明')),
        'link' => '#emr/station', 'ref_type' => 'template_result', 'ref_id' => (int)$id,
    ]);
}
Response::success(['id' => $id]);