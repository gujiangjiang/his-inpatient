<?php
// api/modules/admin/integration_config_save.php - 保存集成连接配置 (含变更审计, 敏感值仅记录状态)
Auth::requireRole(['admin']);
$baseUrl = trim((string)Validator::get('opd_base_url', ''));
$viewerUrl = trim((string)Validator::get('pacs_viewer_url', ''));
$opdEnabled = (int)Validator::get('opd_enabled', 0) ? '1' : '0';
$pacsEnabled = (int)Validator::get('pacs_enabled', 0) ? '1' : '0';

if ($baseUrl !== '' && !preg_match('#^https?://#', $baseUrl)) Response::error('门诊服务地址须以 http:// 或 https:// 开头');
if ($opdEnabled === '1' && $baseUrl === '') Response::error('启用门诊接口时须填写服务地址');
if ($viewerUrl !== '' && !preg_match('#^https?://#', $viewerUrl)) Response::error('PACS 阅片地址须以 http:// 或 https:// 开头');
if ($viewerUrl !== '' && strpos($viewerUrl, '{study_uid}') === false) Response::error('PACS 阅片地址模板须包含 {study_uid} 占位符');

$new = [
    'opd_enabled' => $opdEnabled,
    'opd_base_url' => rtrim($baseUrl, '/'),
    'opd_fhir_token' => trim((string)Validator::get('opd_fhir_token', '')),
    'opd_his_token' => trim((string)Validator::get('opd_his_token', '')),
    'pacs_enabled' => $pacsEnabled,
    'pacs_viewer_url' => $viewerUrl,
];
$old = opd_settings();
opd_settings_save($new);

// 变更审计: 配置值直接记录, Token 等敏感值仅记录"已更新/已清空"
$labels = [
    'opd_enabled' => '门诊接口', 'opd_base_url' => '门诊服务地址',
    'opd_fhir_token' => 'FHIR Token', 'opd_his_token' => '业务 Token',
    'pacs_enabled' => 'PACS 阅片', 'pacs_viewer_url' => '阅片地址模板',
];
$changes = [];
foreach ($new as $k => $v) {
    if ((string)($old[$k] ?? '') === (string)$v) continue;
    if (in_array($k, ['opd_enabled', 'pacs_enabled'], true)) {
        $changes[] = $labels[$k] . ': ' . ($v === '1' ? '已启用' : '已停用');
    } elseif (strpos($k, 'token') !== false) {
        $changes[] = $labels[$k] . ': ' . ($v === '' ? '已清空' : '已更新');
    } else {
        $changes[] = $labels[$k] . ': ' . ($v === '' ? '（清空）' : $v);
    }
}
if ($changes) {
    $user = Auth::user();
    DB::insert('integration_audit', [
        'actor_name' => $user['name'] ?? '',
        'area' => 'integration',
        'detail' => implode('；', $changes),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'created_at' => DateHelper::now(),
    ]);
}

Response::success(['changes' => count($changes)], '配置已保存');
