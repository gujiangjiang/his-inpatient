<?php
// api/modules/admin/query.php - 管理员数据查询 (诊断/检验/检查/药品/省市县/职业大典/字典)
// 诊断与目录数据: 优先外部门诊一体化接口, 失败/为空自动回退本地内置数据
Auth::requireRole(['admin']);
$kind = (string)Validator::get('kind', '');
$kw = trim((string)Validator::get('kw', ''));
$limit = max(1, min(100, (int)Validator::get('limit', 50) ?: 50));

/** 本地 ICD-10 回退检索 (data/icd10.sqlite; FTS5 短语前缀 + LIKE) */
function admin_query_icd_local($kw, $limit)
{
    if ($kw === '') return [];
    $pdo = new PDO('sqlite:' . ICD_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $out = [];
    try {
        $tokens = preg_split('/\s+/u', $kw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        foreach ($tokens as $tk) {
            $tk = str_replace('"', '', $tk);
            if ($tk !== '') $parts[] = '"' . $tk . '"*';
        }
        if ($parts) {
            $stmt = $pdo->prepare("SELECT code, name FROM icd_codes_fts WHERE icd_codes_fts MATCH ? ORDER BY rank LIMIT " . (int)$limit);
            $stmt->execute([implode(' ', $parts)]);
            $out = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) { $out = []; }
    if (count($out) < 10) {
        $stmt = $pdo->prepare("SELECT code, name FROM icd_codes WHERE code LIKE ? OR name LIKE ? ORDER BY code LIMIT " . (int)$limit);
        $stmt->execute(['%' . $kw . '%', '%' . $kw . '%']);
        $seen = [];
        foreach ($out as $r) $seen[$r['code']] = true;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!isset($seen[$r['code']])) { $out[] = $r; $seen[$r['code']] = true; }
        }
    }
    return array_slice($out, 0, $limit);
}

/** 外部门诊 → 本地目录回退 (lab/exam/drug) */
function admin_query_catalog_local($type, $kw, $limit)
{
    $map = ['lab' => 'lab_catalog', 'exam' => 'exam_catalog', 'drug' => 'drug_catalog'];
    $table = $map[$type] ?? '';
    if ($table === '') return [];
    $sql = "SELECT name, " . ($table === 'drug_catalog' ? "IFNULL(spec,'')" : "''") . " AS spec, IFNULL(price,0) AS price FROM $table WHERE 1=1";
    $args = [];
    if ($kw !== '') { $sql .= " AND name LIKE ?"; $args[] = '%' . $kw . '%'; }
    $sql .= " ORDER BY name LIMIT " . (int)$limit;
    $stmt = DB::getPDO()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

switch ($kind) {
    case 'diagnosis':
        /* 外部优先 (门诊一体化 icd_search), 失败/为空回退本地 ICD */
        $rows = [];
        $src = '本地 ICD-10（内置）';
        if (opd_settings()['opd_enabled'] === '1') {
            $r = opd_his_read('icd_search', ['kw' => $kw, 'limit' => $limit, 'offset' => 0]);
            $list = $r['data']['list'] ?? $r['data'] ?? [];
            if ($r['ok'] && is_array($list) && count($list)) {
                foreach ($list as $x) {
                    $rows[] = ['code' => (string)($x['code'] ?? ''), 'name' => (string)($x['name'] ?? '')];
                }
                $src = '门诊一体化（外部接口）';
            }
        }
        if (!$rows) $rows = admin_query_icd_local($kw, $limit);
        Response::success(['source' => $src, 'rows' => $rows]);
    case 'lab': case 'exam': case 'drug':
        $typeMap = ['lab' => 'lab_items', 'exam' => 'exam_items', 'drug' => 'drugs'];
        $rows = [];
        $src = '本地目录（内置）';
        if (opd_settings()['opd_enabled'] === '1') {
            $r = opd_his_read('catalog_list', ['type' => $typeMap[$kind]]);
            $list = $r['data']['list'] ?? $r['data'] ?? [];
            if ($r['ok'] && is_array($list)) {
                foreach ($list as $x) {
                    $name = (string)($x['name'] ?? '');
                    if ($kw !== '' && mb_stripos($name, $kw) === false) continue;
                    $rows[] = ['name' => $name, 'spec' => (string)($x['spec'] ?? ''), 'price' => $x['price'] ?? ''];
                    if (count($rows) >= $limit) break;
                }
                if ($rows) $src = '门诊一体化（外部接口）';
            }
        }
        if (!$rows) $rows = admin_query_catalog_local($kind, $kw, $limit);
        Response::success(['source' => $src, 'rows' => $rows]);
    case 'region':
        $province = trim((string)Validator::get('province', ''));
        Response::success(['source' => '独立字典库（省市县+邮编）', 'rows' => dict_region_search($kw, $province, $limit)]);
    case 'occupation':
        $level = (int)Validator::get('level', 0);
        Response::success(['source' => '职业分类大典（2022年版）', 'rows' => dict_occupation_search($kw, $level, $limit), 'stats' => dict_occupation_stats()]);
    case 'dict':
        $key = (string)Validator::get('key', '');
        $meta = emr_dict_meta();
        if ($key === '') {
            $items = $meta;
        } else {
            $items = [];
            foreach ($meta as $d) { if ($d['dkey'] === $key) { $items = $d['items']; break; } }
        }
        Response::success(['source' => '独立字典库（院级字典）', 'rows' => is_array($items) && $items && isset($items[0]['dkey']) ? $items : $items]);
    default:
        Response::error('未知查询类型');
}
