<?php
// api/modules/emr/content_save.php - 内容库条目保存 (新建/更新)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$name = trim((string)Validator::get('name', ''));
$categoryId = Validator::get('category_id');
$content = (string)Validator::get('content', '');
$id = isset($data['id']) && $data['id'] ? (int)$data['id'] : null;

if ($name === '') Response::error('请输入内容标题');

$pdo = DB::getPDO();
if ($id) {
    $existing = DB::selectOne("SELECT id FROM emr_contents WHERE id = ?", [$id]);
    if (!$existing) Response::error('内容不存在', 404);
    $stmt = $pdo->prepare("UPDATE emr_contents SET name=?, category_id=?, content=?, updated_at=? WHERE id=?");
    $stmt->execute([$name, $categoryId ?: null, $content, DateHelper::now(), $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO emr_contents (name, category_id, content, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$name, $categoryId ?: null, $content, Auth::user()['id'], DateHelper::now(), DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
