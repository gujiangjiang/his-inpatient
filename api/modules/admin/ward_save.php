<?php
// api/modules/admin/ward_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$name = Validator::get('name');
$code = Validator::get('code');

if (!$name || !$code) {
    Response::error('请输入病区名称和编码');
}

$pdo = DB::getPDO();
$id = isset($data['id']) ? $data['id'] : null;
$departmentId = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE wards SET code=?, name=?, floor=?, bed_count=?, department_id=?, is_active=? WHERE id=?");
    $stmt->execute([$code, $name, $data['floor'] ?? '', $data['bed_count'] ?? 0, $departmentId, $data['is_active'] ?? 1, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO wards (code, name, floor, bed_count, department_id, is_active, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$code, $name, $data['floor'] ?? '', $data['bed_count'] ?? 0, $departmentId, $data['is_active'] ?? 1, DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
