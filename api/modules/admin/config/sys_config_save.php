<?php
// api/modules/admin/sys_config_save.php - 保存配置 (未提供的分组/描述保留原值, 避免编辑值清空元数据)
Auth::requireRole(['admin']);
require_once __DIR__ . '/../../../core/branding.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('仅支持 POST 请求', 405);
$key = Validator::get('config_key');
$value = Validator::get('config_value');

if (!$key || $value === null) {
    Response::error('请输入配置键和值');
}

$blocked = ['setup_completed', 'emr_templates_version', 'hospital_brand_revision'];
if (in_array($key, $blocked, true)) Response::error('该配置项不可手工修改');
$existing = DB::selectOne("SELECT * FROM system_config WHERE config_key = ?", [$key]);
if (!$existing) Response::error('配置项不存在');
$value = trim((string)$value);
$len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
if (preg_match('/[\x00-\x1F\x7F]/u', $value)) Response::error('配置值不能包含控制字符');
if ($key === 'hospital_name' && $value === '') Response::error('医院名称为必填项');
if (in_array($key, ['hospital_name', 'hospital_name2'], true) && $len > 100) Response::error('医院名称不能超过 100 个字符');
if ($len > 2000) Response::error('配置值过长');
DB::execute(
    "UPDATE system_config SET config_value=?, updated_at=? WHERE config_key=?",
    [$value, DateHelper::now(), $key]
);
if (in_array($key, ['hospital_name', 'hospital_name2'], true)) branding_bump_revision();
Response::success(['key' => $key]);
