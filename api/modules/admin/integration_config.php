<?php
// api/modules/admin/integration_config.php - 集成连接配置读取 (门诊一体化 / PACS)
Auth::requireRole(['admin']);
$s = opd_settings();
Response::success([
    'opd_enabled' => $s['opd_enabled'] === '1',
    'opd_base_url' => $s['opd_base_url'],
    'opd_fhir_token' => $s['opd_fhir_token'],
    'opd_his_token' => $s['opd_his_token'],
    'pacs_enabled' => $s['pacs_enabled'] === '1',
    'pacs_viewer_url' => $s['pacs_viewer_url'],
]);
