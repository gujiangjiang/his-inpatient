<?php
// api/modules/emr/icd_search.php - ICD-10 检索 (优先门诊一体化标准编码库, 不可用时回退本地字典)
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']);
$keyword = trim((string)Validator::get('keyword', ''));

if ($keyword === '') {
    Response::success([]);
}

// 门诊一体化标准 ICD-10 编码库 (动态)
if (opd_settings()['opd_enabled'] === '1') {
    $r = opd_his_read('icd_search', ['kw' => $keyword, 'limit' => 20]);
    if ($r['ok'] && isset($r['data']['list']) && is_array($r['data']['list']) && count($r['data']['list'])) {
        Response::json([
            'status' => 'success',
            'message' => '',
            'data' => array_values($r['data']['list']),
            'source' => 'opd',
        ]);
    }
}

// 本地字典回退 (FTS5 + LIKE 双通道, data/icd10.sqlite)
$keywords = explode(' ', $keyword);
$ftsQuery = implode(' ', array_map(fn($k) => $k . '*', $keywords));

$pdo = new PDO('sqlite:' . ICD_DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$results = [];
$stmt = $pdo->prepare("SELECT code, name FROM icd_codes_fts WHERE icd_codes_fts MATCH ? ORDER BY rank LIMIT 20");
$stmt->execute([$ftsQuery]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($results) < 10) {
    $likeStmt = $pdo->prepare("SELECT code, name FROM icd_codes WHERE code LIKE ? OR name LIKE ? ORDER BY code LIMIT 20");
    $likeStmt->execute(['%' . $keyword . '%', '%' . $keyword . '%']);
    $more = $likeStmt->fetchAll(PDO::FETCH_ASSOC);
    $seen = [];
    foreach ($results as $r) {
        $seen[$r['code']] = true;
    }
    foreach ($more as $r) {
        if (!isset($seen[$r['code']])) {
            $results[] = $r;
            $seen[$r['code']] = true;
        }
    }
}

usort($results, function($a, $b) {
    return strcmp($a['code'], $b['code']);
});

Response::json([
    'status' => 'success',
    'message' => '',
    'data' => array_slice($results, 0, 20),
    'source' => 'local',
]);
