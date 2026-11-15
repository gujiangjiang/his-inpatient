<?php
// api/modules/emr/document/maintenance_review.php - 病历维护申请审核 (管理员)
// approve: edit → 文书退回草稿(可编辑); delete → 删除文书及历史版本
// reject: 附原因退回 (申请人可重新申请)
Auth::requireRole(['admin']);
$id = (int)Validator::get('id', 0);
$action = Validator::get('action');
if (!$id || !in_array($action, ['approve', 'reject'], true)) Response::error('参数不完整');

$row = DB::selectOne("SELECT * FROM emr_maintenance_requests WHERE id = ?", [$id]);
if (!$row) Response::error('申请不存在', 404);
if ($row['status'] !== 'pending') Response::error('仅待审核申请可操作', 400);

$uid = Auth::user()['id'];
$now = DateHelper::now();
$pdo = DB::getPDO();
$reason = trim((string)Validator::get('reason', ''));

if ($action === 'reject') {
    $pdo->prepare("UPDATE emr_maintenance_requests SET status='rejected', reviewed_by=?, reviewed_at=?, reject_reason=? WHERE id=?")
        ->execute([$uid, $now, $reason, $id]);
    /* 推送: 管理员待办消除 + 通知申请人驳回 */
    notify_resolve('maintenance', $id);
    if (!empty($row['requested_by'])) {
        notify_send([
            'user_ids' => [(int)$row['requested_by']],
            'type' => 'maintenance', 'title' => '病历维护申请被驳回',
            'body' => '原因：' . ($reason !== '' ? $reason : '未注明') . '（可在左下角「维护」查看）',
            'link' => '#emr/station',
        ]);
    }
    Response::success(['id' => $id]);
}

/* 批准: 按申请类型执行 (edit 解锁 / delete 删除) */
$doc = DB::selectOne("SELECT * FROM emr_documents WHERE id = ?", [(int)$row['doc_id']]);
if (!$doc) Response::error('文书已不存在', 400);

$pdo->beginTransaction();
try {
    if ($row['action'] === 'delete') {
        $pdo->prepare("DELETE FROM emr_document_versions WHERE doc_id = ?")->execute([(int)$doc['id']]);
        $pdo->prepare("DELETE FROM emr_documents WHERE id = ?")->execute([(int)$doc['id']]);
    } else {
        /* edit: 退回草稿解锁 (内容与历史版本保留; 修改后需重新签名); 打「修订留痕」标记:
           之后删除的文字以红色删除线保留在文书中 (可追溯), 打印/快照不输出 */
        $meta = json_decode((string)($doc['doc_meta'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $meta['revise'] = true;
        $pdo->prepare("UPDATE emr_documents SET status = 'draft', doc_meta = ?, updated_at = ? WHERE id = ?")
            ->execute([json_encode($meta, JSON_UNESCAPED_UNICODE), $now, (int)$doc['id']]);
    }
    $pdo->prepare("UPDATE emr_maintenance_requests SET status='approved', reviewed_by=?, reviewed_at=?, reject_reason=NULL WHERE id=?")
        ->execute([$uid, $now, $id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('维护处理失败: ' . $e->getMessage());
}
/* 推送: 管理员待办消除 + 通知申请人已批准 (批准编辑→可打开修改) */
notify_resolve('maintenance', $id);
if (!empty($row['requested_by'])) {
    notify_send([
        'user_ids' => [(int)$row['requested_by']],
        'type' => 'maintenance',
        'title' => '病历维护申请已批准（' . ($row['action'] === 'delete' ? '删除' : '编辑') . '）',
        'body' => $row['action'] === 'edit' ? '病历已退回草稿，可在左下角「维护」中一键打开修改（修订留痕）' : '病历已按申请删除',
        'link' => '#emr/station',
    ]);
}
Response::success(['id' => $id, 'action' => $row['action']]);
