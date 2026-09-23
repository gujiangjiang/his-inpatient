<?php
// api/modules/emr/category_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$name = Validator::get('name');
$code = Validator::get('code');
if (!$name || !$code) Response::error('请输入分类名称和编码');

$pdo = DB::getPDO();
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;
if ($id) {
    DB::execute("UPDATE emr_categories SET name=?, code=?, sort_order=?, is_active=? WHERE id=?",
        [$name, $code, $data['sort_order'] ?? 0, $data['is_active'] ?? 1, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO emr_categories (name, code, sort_order, is_active, created_at) VALUES (?,?,?,?,?)");
    $stmt->execute([$name, $code, $data['sort_order'] ?? 0, $data['is_active'] ?? 1, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);