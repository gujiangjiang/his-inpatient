<?php
// api/modules/admin/department_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$name = Validator::get('name');
$code = Validator::get('code');

if (!$name || !$code) {
    Response::error('请输入科室名称和编码');
}

$pdo = DB::getPDO();
$id = isset($data['id']) ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE departments SET code=?, name=?, description=?, is_active=? WHERE id=?");
    $stmt->execute([$code, $name, $data['description'] ?? '', $data['is_active'] ?? 1, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO departments (code, name, description, is_active, created_at) VALUES (?,?,?,?,?)");
    $stmt->execute([$code, $name, $data['description'] ?? '', $data['is_active'] ?? 1, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
