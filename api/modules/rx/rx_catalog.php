<?php
// api/modules/rx/rx_catalog.php - 药品项目库 (优先门诊一体化同步, 不可用时回退 HIS 药品目录)
Auth::requireRole(['admin', 'doctor', 'pharmacist']);
$kw = trim((string)Validator::get('kw', ''));
$categoryFilter = trim((string)Validator::get('category', ''));
$limit = max(1, min(200, (int)Validator::get('limit', 30) ?: 30));
$offset = max(0, (int)Validator::get('offset', 0));

$source = 'local';
if (opd_settings()['opd_enabled'] === '1') {
    $sync = catalog_sync_from_opd('drugs');
    if ($sync['ok']) $source = 'opd';
}

if ($source === 'opd') {
    $rows = DB::select("SELECT * FROM drug_catalog WHERE is_active = 1 AND opd_id IS NOT NULL ORDER BY category, name");
} else {
    // HIS 本地药品目录映射为统一结构
    $rows = [];
    foreach (DB::select("SELECT * FROM medications ORDER BY name") as $m) {
        $freqText = (string)($m['default_freq'] ?? '');
        $rows[] = [
            'id' => (int)$m['id'],
            'name' => (string)$m['name'],
            'spec' => (string)($m['specification'] ?? ''),
            'unit' => (string)($m['unit'] ?? ''),
            'price' => (float)$m['price'],
            'vendor' => '',
            'vendor_short' => '',
            'category' => (string)($m['drug_class'] ?? '西药'),
            'form' => (string)($m['dosage_form'] ?? ''),
            'single_dose' => (string)($m['default_dosage'] ?? ''),
            'frequency_code' => freq_code_from_text($freqText),
            'frequency_text' => $freqText,
            'route' => (string)($m['default_route'] ?? ''),
        ];
    }
}

if ($kw !== '') {
    $rows = array_values(array_filter($rows, function ($r) use ($kw) {
        return mb_stripos($r['name'], $kw) !== false
            || mb_stripos($r['spec'] ?? '', $kw) !== false
            || mb_stripos($r['vendor_short'] ?? '', $kw) !== false
            || mb_stripos($r['category'] ?? '', $kw) !== false;
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
