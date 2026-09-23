<?php
// api/modules/admin/api_config_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$name = Validator::get('name');
$apiType = Validator::get('api_type');
$baseUrl = Validator::get('base_url');

if (!$name || !$apiType) {
    Response::error('请输入接口名称和类型');
}

$pdo = DB::getPDO();
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE api_configs SET name=?, api_type=?, base_url=?, auth_type=?, auth_config=?, is_active=? WHERE id=?");
    $stmt->execute([
        $name, $apiType, $baseUrl,
        $data['auth_type'] ?? 'none',
        $data['auth_config'] ?? '',
        $data['is_active'] ?? 1,
        $id
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO api_configs (name, api_type, base_url, auth_type, auth_config, is_active, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $name, $apiType, $baseUrl,
        $data['auth_type'] ?? 'none',
        $data['auth_config'] ?? '',
        $data['is_active'] ?? 1,
        DateHelper::now()
    ]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
