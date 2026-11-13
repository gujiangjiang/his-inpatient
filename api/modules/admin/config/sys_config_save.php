<?php
// api/modules/admin/sys_config_save.php - 保存配置 (未提供的分组/描述保留原值, 避免编辑值清空元数据)
Auth::requireRole(['admin']);
$data = Validator::all();
$key = Validator::get('config_key');
$value = Validator::get('config_value');

if (!$key || $value === null) {
    Response::error('请输入配置键和值');
}

$existing = DB::selectOne("SELECT * FROM system_config WHERE config_key = ?", [$key]);
if ($existing) {
    $group = array_key_exists('config_group', $data) && $data['config_group'] !== null
        ? (string)$data['config_group'] : (string)$existing['config_group'];
    $desc = array_key_exists('description', $data) && $data['description'] !== null
        ? (string)$data['description'] : (string)$existing['description'];
    DB::execute(
        "UPDATE system_config SET config_value=?, config_group=?, description=?, updated_at=? WHERE config_key=?",
        [$value, $group, $desc, DateHelper::now(), $key]
    );
} else {
    DB::execute(
        "INSERT INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
        [$key, $value, $data['config_group'] ?? 'system', $data['description'] ?? '', DateHelper::now()]
    );
}
Response::success(['key' => $key]);
