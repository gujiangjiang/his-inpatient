<?php
// api/modules/emr/icd_search.php
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']);
$keyword = Validator::get('keyword', '');

if (!$keyword) {
    Response::success([]);
}

// ICD-10 搜索：FTS5 + LIKE 双通道
$keywords = explode(' ', $keyword);
$ftsQuery = implode(' ', array_map(fn($k) => $k . '*', $keywords));

$pdo = new PDO('sqlite:' . ICD_DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// FTS5 前缀搜索
$results = [];
$stmt = $pdo->prepare("SELECT code, name FROM icd_codes_fts WHERE icd_codes_fts MATCH ? ORDER BY rank LIMIT 20");
$stmt->execute([$ftsQuery]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// LIKE 补充搜索
if (count($results) < 10) {
    $likeStmt = $pdo->prepare("SELECT code, name FROM icd_codes WHERE code LIKE ? OR name LIKE ? ORDER BY code LIMIT 20");
    $likeStmt->execute(['%' . $keyword . '%', '%' . $keyword . '%']);
    $more = $likeStmt->fetchAll(PDO::FETCH_ASSOC);
    // 合并去重
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

// 按是否常见分类排序
usort($results, function($a, $b) {
    // 优先返回匹配度更高的结果
    return strcmp($a['code'], $b['code']);
});

Response::success(array_slice($results, 0, 20));
