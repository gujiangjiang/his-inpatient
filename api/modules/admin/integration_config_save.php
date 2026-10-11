<?php
// api/modules/admin/integration_config_save.php - 保存集成连接配置
Auth::requireRole(['admin']);
$baseUrl = trim((string)Validator::get('opd_base_url', ''));
$viewerUrl = trim((string)Validator::get('pacs_viewer_url', ''));
$opdEnabled = (int)Validator::get('opd_enabled', 0) ? '1' : '0';
$pacsEnabled = (int)Validator::get('pacs_enabled', 0) ? '1' : '0';

if ($baseUrl !== '' && !preg_match('#^https?://#', $baseUrl)) Response::error('门诊服务地址须以 http:// 或 https:// 开头');
if ($opdEnabled === '1' && $baseUrl === '') Response::error('启用门诊接口时须填写服务地址');
if ($viewerUrl !== '' && !preg_match('#^https?://#', $viewerUrl)) Response::error('PACS 阅片地址须以 http:// 或 https:// 开头');
if ($viewerUrl !== '' && strpos($viewerUrl, '{study_uid}') === false) Response::error('PACS 阅片地址模板须包含 {study_uid} 占位符');

opd_settings_save([
    'opd_enabled' => $opdEnabled,
    'opd_base_url' => rtrim($baseUrl, '/'),
    'opd_fhir_token' => trim((string)Validator::get('opd_fhir_token', '')),
    'opd_his_token' => trim((string)Validator::get('opd_his_token', '')),
    'pacs_enabled' => $pacsEnabled,
    'pacs_viewer_url' => $viewerUrl,
]);
Response::success(null, '配置已保存');
