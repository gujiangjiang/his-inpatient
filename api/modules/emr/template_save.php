<?php
// api/modules/emr/template_save.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$name = Validator::get('name');
$recordType = Validator::get('record_type');
$contentDelta = Validator::get('content_delta');
$contentHtml = Validator::get('content_html');
$schemaJson = Validator::get('schema_json');   // 字处理编辑器模板 schema (可选)
$categoryId = Validator::get('category_id');

if (!$name || !$recordType || !$contentDelta) {
    Response::error('缺少必要参数');
}

// schema_json 需为合法 JSON
if ($schemaJson !== null && $schemaJson !== '') {
    $schemaArr = is_string($schemaJson) ? json_decode($schemaJson, true) : $schemaJson;
    if (!is_array($schemaArr)) Response::error('schema_json 格式错误');
    $schemaJson = is_string($schemaJson) ? $schemaJson : json_encode($schemaArr, JSON_UNESCAPED_UNICODE);
}

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE emr_templates SET name=?, content_delta=?, content_html=?, schema_json=?, category_id=? WHERE id=?");
    $stmt->execute([$name, $contentDelta, $contentHtml, $schemaJson, $categoryId, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO emr_templates (name, record_type, content_delta, content_html, schema_json, category_id, usage_count, created_by, created_at) VALUES (?,?,?,?,?,?,0,?,?)");
    $stmt->execute([$name, $recordType, $contentDelta, $contentHtml, $schemaJson, $categoryId, $userId, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
