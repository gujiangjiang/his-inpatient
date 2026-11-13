<?php
// api/modules/admin/integration_test.php - 门诊一体化 / PACS 连通性测试
Auth::requireRole(['admin']);
$s = opd_settings();
$items = [];

// 1. 门诊 FHIR 元数据 (免认证)
list($code, $body) = opd_http('GET', opd_base_url() . '/api/fhir/r4/metadata', ['Accept: application/fhir+json'], null, 8);
$json = json_decode((string)$body, true);
$metaOk = $code === 200 && is_array($json) && ($json['resourceType'] ?? '') === 'CapabilityStatement';
$items[] = ['name' => '门诊 FHIR 服务', 'ok' => $metaOk, 'detail' => $metaOk ? ('在线 · FHIR ' . ($json['fhirVersion'] ?? '')) : ('HTTP ' . $code)];

// 2. 门诊 FHIR 令牌 (带鉴权读取一个患者)
if ($s['opd_fhir_token'] !== '') {
    $resp2 = opd_http('GET', opd_base_url() . '/api/fhir/r4/Patient?_count=1', [
        'Authorization: Bearer ' . $s['opd_fhir_token'],
        'X-API-Key: ' . $s['opd_fhir_token'],
    ], null, 8);
    $code2 = (int)$resp2[0];
    $items[] = [
        'name' => '门诊 FHIR 令牌',
        'ok' => $code2 === 200,
        'detail' => $code2 === 200 ? '令牌有效' : ('HTTP ' . $code2 . '（检查 Token / Scope / IP 白名单）'),
    ];
} else {
    $items[] = ['name' => '门诊 FHIR 令牌', 'ok' => false, 'detail' => '未配置 Token'];
}

// 3. 门诊业务只读接口 (ping)
$ping = opd_his_read('ping');
$items[] = [
    'name' => '门诊业务接口',
    'ok' => $ping['ok'],
    'detail' => $ping['ok'] ? ('在线 · ' . ($ping['data']['system'] ?? '')) : $ping['msg'],
];

// 4. PACS 阅片服务
$viewerBase = $s['pacs_viewer_url'] !== '' ? strtok($s['pacs_viewer_url'], '?') : '';
$resp4 = $viewerBase !== '' ? opd_http('GET', $viewerBase, [], null, 8) : [0, ''];
$code4 = (int)$resp4[0];
$items[] = ['name' => 'PACS 阅片服务', 'ok' => $code4 === 200, 'detail' => $code4 === 200 ? '在线' : ('HTTP ' . $code4 . '（检查阅片地址）')];

$allOk = true;
foreach ($items as $it) {
    if (!$it['ok']) $allOk = false;
}
// 记录最近一次测试结果 (状态总览展示)
opd_settings_save([
    'last_test_at' => DateHelper::now(),
    'last_test_ok' => $allOk ? '1' : '0',
]);
Response::success(['ok' => $allOk, 'items' => $items]);
