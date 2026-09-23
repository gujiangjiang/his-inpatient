<?php
// api/modules/emr/document_get.php
Auth::requireRole(['admin', 'doctor']);
$id = Validator::get('id');
if (!$id) Response::error('请输入文书ID');

$doc = DB::selectOne("SELECT d.*, c.name as category_name, t.name as template_name,
    u.name as created_name, s.name as signer_name, s.name as signer_title
    FROM emr_documents d
    LEFT JOIN emr_categories c ON d.category_id = c.id
    LEFT JOIN emr_templates t ON d.template_id = t.id
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN users s ON d.signer_id = s.id
    WHERE d.id = ?", [$id]);

if (!$doc) Response::error('文书不存在', 404);

// 解析 JSON 字段
$doc['doc_meta'] = json_decode($doc['doc_meta'] ?? '{}', true) ?: [];
$doc['sections'] = json_decode($doc['sections'] ?? '[]', true) ?: [];

Response::success($doc);