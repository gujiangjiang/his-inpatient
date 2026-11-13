<?php
// api/modules/emr/version_restore.php - 恢复文书历史版本 (仅草稿, 已签名禁止)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$versionId = Validator::get('version_id');
if (!$versionId) Response::error('缺少版本ID');

$pdo = DB::getPDO();
$version = DB::selectOne("SELECT * FROM emr_document_versions WHERE id = ?", [$versionId]);
if (!$version) Response::error('版本不存在', 404);

$doc = DB::selectOne("SELECT * FROM emr_documents WHERE id = ?", [$version['doc_id']]);
if (!$doc) Response::error('文书不存在');
if ($doc['status'] === 'signed') Response::error('文书已签名锁定, 禁止恢复历史', 403);

/* 守卫校验: 历史版本 doc_json 的固定元素键必须与文书固化守卫一致 (防篡改版本被恢复) */
$meta = json_decode($doc['doc_meta'] ?? '', true);
$guard = is_array($meta) && isset($meta['guard']) ? $meta['guard'] : null;
if (is_array($guard)) {
    $restoreDoc = json_decode($version['doc_json'], true);
    if (is_array($restoreDoc) && is_array($restoreDoc['body'] ?? null)) {
        $guardErr = emrGuardValidate($restoreDoc['body'], $guard);
        if ($guardErr !== '') Response::error('该历史版本与固化守卫不一致, 禁止恢复: ' . $guardErr);
    }
}

$userId = Auth::user()['id'];
$now = DateHelper::now();
$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE emr_documents SET doc_json = ?, content_html = ?, updated_at = ? WHERE id = ?")
        ->execute([$version['doc_json'], $version['content_html'], $now, $doc['id']]);
    // 恢复动作落一版历史
    $pdo->prepare("INSERT INTO emr_document_versions (doc_id, doc_json, content_html, action, created_by, created_at) VALUES (?,?,?, 'restore', ?, ?)")
        ->execute([$doc['id'], $version['doc_json'], $version['content_html'], $userId, $now]);
    $pdo->commit();
    Response::success(['id' => $doc['id']]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('恢复失败: ' . $e->getMessage());
}