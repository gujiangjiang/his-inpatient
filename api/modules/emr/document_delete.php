<?php
// api/modules/emr/document_delete.php
// 服务端强制校验: signed 状态文档禁止物理删除
Auth::requireRole(['admin', 'doctor']);
$id = Validator::get('id');
if (!$id) Response::error('请输入文书ID');

$doc = DB::selectOne("SELECT * FROM emr_documents WHERE id = ?", [$id]);
if (!$doc) Response::error('文书不存在');
if ($doc['status'] === 'signed') {
    Response::error('文书已签名锁定, 禁止删除', 403);
}

DB::delete('emr_documents', 'id = ?', [$id]);
Response::success();