<?php
// api/modules/emr/content_save.php - 内容库条目保存 (新建/更新)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$name = trim((string)Validator::get('name', ''));
$categoryId = Validator::get('category_id');
$content = (string)Validator::get('content', '');
$departmentId = Validator::get('department_id'); // 科室归属; null=自动(新建取本人科室, 更新保持原值), 0=全院通用
$id = isset($data['id']) && $data['id'] ? (int)$data['id'] : null;

if ($name === '') Response::error('请输入内容标题');

$pdo = DB::getPDO();
if ($id) {
    $existing = DB::selectOne("SELECT id, department_id, created_by FROM emr_contents WHERE id = ?", [$id]);
    $cur = Auth::user();
    if ($cur['role'] !== 'admin' && $existing && (int)$existing['created_by'] !== (int)$cur['id']) Response::error('仅创建人可编辑', 403);
    if (!$existing) Response::error('内容不存在', 404);
    $departmentId = resolve_department_id($departmentId, $existing['department_id']);
    $stmt = $pdo->prepare("UPDATE emr_contents SET name=?, category_id=?, content=?, department_id=?, updated_at=? WHERE id=?");
    $stmt->execute([$name, $categoryId ?: null, $content, $departmentId, DateHelper::now(), $id]);
} else {
    $departmentId = resolve_department_id($departmentId, null);
    $stmt = $pdo->prepare("INSERT INTO emr_contents (name, category_id, content, department_id, created_by, created_at, updated_at, pub_status) VALUES (?,?,?,?,?,?,?,'personal')");
    $stmt->execute([$name, $categoryId ?: null, $content, $departmentId, Auth::user()['id'], DateHelper::now(), DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
