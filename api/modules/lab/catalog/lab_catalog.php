<?php
// api/modules/lab/lab_catalog.php - 检验项目库 (优先门诊一体化同步, 不可用时回退本地)
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$kw = trim((string)Validator::get('kw', ''));
$categoryFilter = trim((string)Validator::get('category', ''));
$limit = max(1, min(200, (int)Validator::get('limit', 30) ?: 30));
$offset = max(0, (int)Validator::get('offset', 0));

$source = 'local';
if (opd_settings()['opd_enabled'] === '1') {
    $sync = catalog_sync_from_opd('lab_items');
    if ($sync['ok']) $source = 'opd';
}

$sql = "SELECT * FROM lab_catalog WHERE is_active = 1";
$params = [];
if ($source === 'opd') {
    $sql .= " AND opd_id IS NOT NULL";
    $sql .= " ORDER BY category, name";
} else {
    $sql .= " ORDER BY sort_no, id";
}
$rows = DB::select($sql, $params);
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['price'] = (float)$r['price'];
    $r['is_group'] = (int)($r['is_group'] ?? 0);
    $r['members'] = json_decode((string)($r['members_json'] ?? '[]'), true) ?: [];
}
unset($r);

// 关键字过滤 (在结果集上过滤, 避免 SQL 方言差异)
if ($kw !== '') {
    $rows = array_values(array_filter($rows, function ($r) use ($kw) {
        return mb_stripos($r['name'], $kw) !== false
            || mb_stripos($r['category'] ?? '', $kw) !== false
            || mb_stripos($r['note'] ?? '', $kw) !== false;
    }));
}

if ($categoryFilter !== '' && $categoryFilter !== '全部') {
    $rows = array_values(array_filter($rows, function ($r) use ($categoryFilter) {
        return ($r['category'] ?? '') === $categoryFilter;
    }));
}

$total = count($rows);
$rows = array_slice($rows, $offset, $limit);

Response::json([
    'status' => 'success',
    'message' => '',
    'data' => $rows,
    'total' => $total,
    'source' => $source,
]);
