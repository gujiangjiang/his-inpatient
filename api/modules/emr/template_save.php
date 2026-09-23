<?php
// api/modules/emr/template_save.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$name = Validator::get('name');
$recordType = Validator::get('record_type');
$contentDelta = Validator::get('content_delta');
$contentHtml = Validator::get('content_html');

if (!$name || !$recordType || !$contentDelta) {
    Response::error('缺少必要参数');
}

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE emr_templates SET name=?, content_delta=?, content_html=? WHERE id=?");
    $stmt->execute([$name, $contentDelta, $contentHtml, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO emr_templates (name, record_type, content_delta, content_html, usage_count, created_by, created_at) VALUES (?,?,?,?,0,?,?)");
    $stmt->execute([$name, $recordType, $contentDelta, $contentHtml, $userId, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
