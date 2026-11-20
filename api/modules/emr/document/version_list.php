<?php
// api/modules/emr/version_list.php - 文书版本历史
Auth::requireRole(['admin', 'doctor']);
$docId = Validator::get('doc_id');
if (!$docId) Response::error('缺少文书ID');

$doc = DB::selectOne("SELECT patient_id FROM emr_documents WHERE id = ?", [$docId]);
if (!$doc) Response::error('文书不存在', 404);
if (!can_access_patient(Auth::user(), (int)$doc['patient_id'])) Response::error('无权访问该文书', 403);

$versions = DB::select(
    "SELECT v.id, v.doc_id, v.action, v.created_at, u.name as created_name
     FROM emr_document_versions v
     LEFT JOIN users u ON v.created_by = u.id
     WHERE v.doc_id = ?
     ORDER BY v.id DESC
     LIMIT 50",
    [$docId]
);
Response::success($versions);