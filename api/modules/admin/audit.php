<?php
// api/modules/admin/audit.php - 审核中心（参照门诊一体化 audit.php）
// action=list|apply|all
Auth::requireRole(['admin']);
$action = Validator::get('action', 'list');

if ($action === 'list') {
    $status = Validator::get('status', 'pending');
    $page = max(1, (int)Validator::get('page', 1) ?: 1);
    $per = max(1, min(100, (int)Validator::get('per', 20) ?: 20));
    $r = audit_list($status, $page, $per);
    // 行渲染（前端纯数据，由 JS 拼 HTML）
    Response::success($r);
}

if ($action === 'apply') {
    $id = (int)Validator::get('id', 0);
    $approve = (int)Validator::get('approve', 0) === 1;
    $note = trim((string)Validator::get('note', ''));
    if (!$id) Response::error('参数错误');
    if (!$approve && $note === '') Response::error('请填写驳回理由');
    list($ok, $msg) = audit_apply($id, $approve, $note);
    if (!$ok) Response::error($msg);
    // 按类型业务流转（药品/设置类：通过→approved 生效）
    $audit = DB::selectOne("SELECT * FROM audits WHERE id = ?", [$id]);
    if ($audit && $approve) {
        if ($audit['type'] === 'item_drug' && $audit['ref_id'] > 0) {
            DB::execute("UPDATE medications SET is_active = 1 WHERE id = ?", [(int)$audit['ref_id']]);
        } elseif ($audit['type'] === 'drugsetting' && $audit['ref_id'] > 0) {
            // 药品设置：目前无独立表，置 ref 生效（预留）
        }
    }
    log_write('operation', 'config', $msg . '审核：' . $audit['title'], $note, 'info', ['action' => 'audit_' . ($approve ? 'approve' : 'reject')]);
    Response::success([], $msg);
}

if ($action === 'all') {
    // 一键全部通过（密码重置/报告撤回不纳入）
    $rows = DB::select("SELECT * FROM audits WHERE status='pending' AND type NOT IN ('pwd_reset','report_withdraw')");
    $n = 0;
    foreach ($rows as $a) {
        list($ok, $msg) = audit_apply((int)$a['id'], true, '');
        if ($ok) $n++;
    }
    Response::success(['count' => $n], '已通过 ' . $n . ' 条');
}
