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
    'last_test_at' => $s['last_test_at'],
    'last_test_ok' => $s['last_test_ok'],
    // 住院检验检查单推送队列统计（接口管理页展示）
    'order_push' => opd_order_push_counts(),
]);
