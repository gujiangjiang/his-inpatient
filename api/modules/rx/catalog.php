<?php
// api/modules/rx/catalog.php - 处方药品项目库（开方时先选药房）
// pharmacy_type=center（默认）：中心药房 medications 实时库存
// pharmacy_type=outpatient：实时调门诊 catalog_list(drugs)，失败降级 opd_drug_cache
Auth::requireRole(['admin', 'doctor', 'pharmacist']);
$kw = trim((string)Validator::get('kw', ''));
$categoryFilter = trim((string)Validator::get('category', ''));
$limit = max(1, min(200, (int)Validator::get('limit', 30) ?: 30));
$offset = max(0, (int)Validator::get('offset', 0));
$pharmacyType = Validator::get('pharmacy_type', 'center');
if (!in_array($pharmacyType, ['center', 'outpatient'], true)) $pharmacyType = 'center';

$source = $pharmacyType;
$rows = [];
$offline = false;

if ($pharmacyType === 'center') {
    // 中心药房：本地库存实时（含规格/拆零/警戒库存）
    foreach (DB::select("SELECT * FROM medications WHERE is_active = 1 ORDER BY name") as $m) {
        $freqText = (string)($m['default_freq'] ?? '');
        $rows[] = [
            'id' => (int)$m['id'],
            'name' => (string)$m['name'],
            'spec' => (string)($m['specification'] ?? ''),
            'unit' => (string)($m['unit'] ?? ''),
            'price' => (float)$m['price'],
            'stock' => (int)($m['stock_quantity'] ?? 0),
            'spec_pack_qty' => (int)($m['spec_pack_qty'] ?? 1),
            'spec_pack_unit' => (string)($m['spec_pack_unit'] ?? ''),
            'allow_split' => (int)($m['allow_split'] ?? 0),
            'vendor' => (string)($m['manufacturer'] ?? ''),
            'vendor_short' => '',
            'category' => (string)($m['drug_class'] ?? '西药'),
            'form' => (string)($m['dosage_form'] ?? ''),
            'single_dose' => (string)($m['default_dosage'] ?? ''),
            'frequency_code' => freq_code_from_text($freqText),
            'frequency_text' => $freqText,
            'route' => (string)($m['default_route'] ?? ''),
            'source' => 'center',
        ];
    }
} else {
    // 门诊药房：实时查 + 降级缓存
    $r = opd_pharmacy_drugs();
    $source = $r['source'];       // opd 实时 / cache 离线缓存 / none
    $offline = !empty($r['offline']);
    foreach (($r['list'] ?? []) as $it) {
        $rows[] = [
            'id' => (int)($it['id'] ?? ($it['opd_id'] ?? 0)),
            'name' => (string)($it['name'] ?? ''),
            'spec' => (string)($it['spec'] ?? ''),
            'unit' => (string)($it['package_unit'] ?? ($it['unit'] ?? '')),
            'price' => (float)($it['price'] ?? 0),
            'stock' => (int)($it['qty'] ?? 0),
            'spec_pack_qty' => (int)($it['spec_pack_qty'] ?? 1),
            'spec_pack_unit' => (string)($it['spec_pack_unit'] ?? ''),
            'allow_split' => (int)($it['allow_split'] ?? 0),
            'vendor' => (string)($it['vendor'] ?? ''),
            'vendor_short' => (string)($it['vendor_short'] ?? ''),
            'category' => (string)($it['category'] ?? '西药'),
            'form' => (string)($it['form'] ?? ''),
            'single_dose' => (string)($it['single_dose'] ?? ''),
            'frequency_code' => freq_code_from_text((string)($it['frequency'] ?? '')),
            'frequency_text' => (string)($it['frequency'] ?? ''),
            'route' => (string)($it['route'] ?? ''),
            'source' => $source,
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
    'offline' => $offline,
    'source_detail' => $source === 'cache' ? '门诊药房实时查询失败，当前为离线缓存（库存/价格可能过期）' : '',
]);
