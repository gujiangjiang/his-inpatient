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
        /* edit: 退回草稿解锁 (内容与历史版本保留; 修改后需重新签名) */
        $pdo->prepare("UPDATE emr_documents SET status = 'draft', updated_at = ? WHERE id = ?")
            ->execute([$now, (int)$doc['id']]);
    }
    $pdo->prepare("UPDATE emr_maintenance_requests SET status='approved', reviewed_by=?, reviewed_at=?, reject_reason=NULL WHERE id=?")
        ->execute([$uid, $now, $id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('维护处理失败: ' . $e->getMessage());
}
Response::success(['id' => $id, 'action' => $row['action']]);
