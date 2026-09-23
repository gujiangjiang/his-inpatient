<?php
// api/modules/admin/sys_config_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$key = Validator::get('config_key');
$value = Validator::get('config_value');

if (!$key || $value === null) {
    Response::error('请输入配置键和值');
}

$pdo = DB::getPDO();
$check = $pdo->prepare("SELECT COUNT(*) as c FROM system_config WHERE config_key = ?");
$check->execute([$key]);

if ($check->fetch()['c'] > 0) {
    $stmt = $pdo->prepare("UPDATE system_config SET config_value=?, config_group=?, description=?, updated_at=? WHERE config_key=?");
    $stmt->execute([
        $value,
        $data['config_group'] ?? '',
        $data['description'] ?? '',
        DateHelper::now(),
        $key
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)");
    $stmt->execute([$key, $value, $data['config_group'] ?? '', $data['description'] ?? '', DateHelper::now()]);
}
Response::success(['key' => $key]);
