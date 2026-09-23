<?php
// api/modules/outpatient/outpatient_search.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$keyword = Validator::get('keyword');
$patientId = Validator::get('patient_id');

// 查询接口配置
$config = DB::selectOne("SELECT * FROM api_configs WHERE api_type = 'outpatient_emr' AND is_active = 1");
$source = 'mock';
$results = [];

if ($config && $config['base_url']) {
    $url = $config['base_url'] . '/search?keyword=' . urlencode($keyword ?? '') . '&patient_id=' . ($patientId ?? '');
    $opts = ['http' => ['method' => 'GET', 'timeout' => 5]];

    if ($config['auth_type'] !== 'none') {
        $authConfig = $config['auth_config'] ? json_decode($config['auth_config'], true) : [];
        if ($config['auth_type'] === 'bearer') {
            $opts['http']['header'] = 'Authorization: Bearer ' . ($authConfig['token'] ?? '');
        } elseif ($config['auth_type'] === 'header') {
            foreach ($authConfig as $k => $v) {
                $opts['http']['header'][] = $k . ': ' . $v;
            }
            $opts['http']['header'] = implode("\r\n", $opts['http']['header'] ?? []);
        }
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response !== false) {
        $decoded = json_decode($response, true);
        if ($decoded && $decoded['status'] === 'success') {
            $results = $decoded['data'] ?? [];
            $source = 'live';
        }
    }
}

// 回退模拟数据
if ($source === 'mock') {
    $results = [
        ['visit_id' => 'V202609230001', 'patient_no' => 'P001', 'name' => '张三', 'date' => '2026-09-15', 'doctor' => '李医生'],
        ['visit_id' => 'V202609230002', 'patient_no' => 'P002', 'name' => '王五', 'date' => '2026-09-18', 'doctor' => '赵医生'],
    ];
    if ($keyword) {
        $results = array_filter($results, fn($r) =>
            stripos($r['name'], $keyword) !== false ||
            stripos($r['patient_no'], $keyword) !== false ||
            stripos($r['visit_id'], $keyword) !== false
        );
    }
}

Response::json([
    'status' => 'success',
    'message' => '',
    'data' => $results,
    'source' => $source
]);
