<?php
// api/core/dict_db.php - 独立基础字典数据库 (data/dicts.sqlite, 与主业务库/配置库分离)
// 内容: 省市县+邮编 (regions, 含区划代码) / 职业分类大典2022 (occupations) / 基础字典 (dicts)
// 数据来源: 打包种子 JSON (api/core/dict_data/*.json, 不写入 PHP 代码); 首次使用自动建库导入。
// 决策: 字典数据体量大且相对静态 — 独立库便于: 主库瘦身、单独更新/备份、与业务库解耦。
// 升级路径: 迁移 057 建库导入并将旧 emr_dicts (主库) 数据合并迁移后移除旧表。

/** 字典库文件路径 */
function dict_db_path()
{
    return DATA_DIR . '/dicts.sqlite';
}

/** 字典库 PDO (首次访问自动建文件/目录) */
function dict_db()
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = dict_db_path();
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');
    return $pdo;
}

/** 种子 JSON 读取 (打包数据, 非 PHP 代码) */
function dict_seed_json($name)
{
    $file = __DIR__ . '/dict_data/' . $name . '.json';
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/** 建表 (幂等) */
function dict_db_schema()
{
    $pdo = dict_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS regions (
        province TEXT NOT NULL, city TEXT NOT NULL, county TEXT NOT NULL,
        zip TEXT, code TEXT, PRIMARY KEY (province, city, county))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS occupations (
        code TEXT PRIMARY KEY, name TEXT NOT NULL, level INTEGER NOT NULL,
        parent_code TEXT DEFAULT '', path TEXT DEFAULT '', gbm TEXT DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dicts (
        dkey TEXT PRIMARY KEY, name TEXT NOT NULL, items TEXT NOT NULL,
        builtin INTEGER DEFAULT 0, updated_at TEXT, updated_by INTEGER)");
}

/** 首次使用: 建库建表 + 种子导入 (各表为空时导入; dicts 仅补齐缺失键, 保留管理员修改) */
function dict_db_ensure()
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        dict_db_schema();
        $pdo = dict_db();
        if ((int)$pdo->query('SELECT COUNT(*) FROM regions')->fetchColumn() === 0) dict_db_import_regions();
        if ((int)$pdo->query('SELECT COUNT(*) FROM occupations')->fetchColumn() === 0) dict_db_import_occupations();
        dict_db_seed_dicts();
    } catch (Exception $e) {
        error_log('[dict_db] ensure 失败: ' . $e->getMessage());
    }
}

/** 导入省市县 (3100+ 条, 事务批量) */
function dict_db_import_regions()
{
    $rows = dict_seed_json('regions');
    if (!$rows) return 0;
    $pdo = dict_db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO regions (province, city, county, zip, code) VALUES (?,?,?,?,?)");
    foreach ($rows as $r) {
        $stmt->execute([$r['p'], $r['c'], $r['a'], (string)($r['z'] ?? ''), (string)($r['c2'] ?? '')]);
    }
    $pdo->commit();
    return count($rows);
}

/** 导入职业分类大典 (层级: 大类/中类/小类/细类, 含完整路径) */
function dict_db_import_occupations()
{
    $packed = dict_seed_json('occupations');
    if (!$packed || empty($packed['categories'])) return 0;
    $pdo = dict_db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO occupations (code, name, level, parent_code, path, gbm) VALUES (?,?,?,?,?,?)");
    $count = 0;
    foreach ($packed['categories'] as $cat) {
        $catCode = (string)($cat[0] ?? '');
        $catName = (string)($cat[1] ?? '');
        $stmt->execute([$catCode, $catName, 1, '', $catName, '']);
        $count++;
        foreach (($cat[2] ?? []) as $mid) {
            $midCode = (string)($mid[0] ?? '');
            $midName = (string)($mid[1] ?? '');
            $stmt->execute([$midCode, $midName, 2, $catCode, $catName . ' > ' . $midName, '']);
            $count++;
            foreach (($mid[2] ?? []) as $minor) {
                $minCode = (string)($minor[0] ?? '');
                $minName = (string)($minor[1] ?? '');
                $stmt->execute([$minCode, $minName, 3, $midCode, $catName . ' > ' . $midName . ' > ' . $minName, '']);
                $count++;
                foreach (($minor[2] ?? []) as $occ) {
                    $occCode = (string)($occ[0] ?? '');
                    $occName = (string)($occ[1] ?? '');
                    $stmt->execute([$occCode, $occName, 4, $minCode, $catName . ' > ' . $midName . ' > ' . $minName . ' > ' . $occName, '']);
                    $count++;
                }
            }
        }
    }
    $pdo->commit();
    return $count;
}

/** 基础字典种子 (补齐缺失键; 已存在的不覆盖 — 保留管理员修改) */
function dict_db_seed_dicts()
{
    $rows = dict_seed_json('dicts');
    if (!$rows) return;
    $pdo = dict_db();
    $stmt = $pdo->prepare("SELECT dkey FROM dicts WHERE dkey = ?");
    $ins = $pdo->prepare("INSERT INTO dicts (dkey, name, items, builtin, updated_at, updated_by) VALUES (?,?,?,1,?,NULL)");
    foreach ($rows as $d) {
        $stmt->execute([$d['key']]);
        if ($stmt->fetchColumn()) continue;
        $ins->execute([$d['key'], $d['name'], json_encode($d['items'], JSON_UNESCAPED_UNICODE), DateHelper::now()]);
    }
}

/** 省市县级联数据: [{p, c, a, z, code}] → 嵌套 {provinces:[{name, cities:[{name, areas:[[county, zip, code]]}]}]} */
function dict_region_tree()
{
    dict_db_ensure();
    $rows = dict_db()->query("SELECT province, city, county, zip, code FROM regions ORDER BY province, city, county")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    $idx = [];
    foreach ($rows as $r) {
        $p = $r['province'];
        if (!isset($idx[$p])) { $idx[$p] = count($out); $out[] = ['name' => $p, 'cities' => []]; }
        $pi = $idx[$p];
        $cities = &$out[$pi]['cities'];
        if (!isset($idx[$p . '|' . $r['city']])) {
            $idx[$p . '|' . $r['city']] = count($cities);
            $cities[] = ['name' => $r['city'], 'areas' => []];
        }
        $ci = $idx[$p . '|' . $r['city']];
        $cities[$ci]['areas'][] = [$r['county'], (string)$r['zip'], (string)$r['code']];
        unset($cities);
    }
    return $out;
}

/** 省市县查询 (管理端): kw 模糊 / province 过滤 */
function dict_region_search($kw = '', $province = '', $limit = 50)
{
    dict_db_ensure();
    $sql = "SELECT province, city, county, zip, code FROM regions WHERE 1=1";
    $args = [];
    if ($province !== '') { $sql .= " AND province = ?"; $args[] = $province; }
    if ($kw !== '') {
        $sql .= " AND (province LIKE ? OR city LIKE ? OR county LIKE ? OR zip LIKE ? OR code LIKE ?)";
        for ($i = 0; $i < 5; $i++) $args[] = '%' . $kw . '%';
    }
    $sql .= " ORDER BY province, city, county LIMIT " . max(1, min(200, (int)$limit));
    $stmt = dict_db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** 职业大典查询 (管理端): kw 模糊(名称/编码) / level 过滤 */
function dict_occupation_search($kw = '', $level = 0, $limit = 50)
{
    dict_db_ensure();
    $sql = "SELECT code, name, level, parent_code, path FROM occupations WHERE 1=1";
    $args = [];
    if ($level > 0) { $sql .= " AND level = ?"; $args[] = (int)$level; }
    if ($kw !== '') {
        $sql .= " AND (name LIKE ? OR code LIKE ?)";
        $args[] = '%' . $kw . '%';
        $args[] = '%' . $kw . '%';
    }
    $sql .= " ORDER BY code LIMIT " . max(1, min(200, (int)$limit));
    $stmt = dict_db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** 职业大典统计 */
function dict_occupation_stats()
{
    dict_db_ensure();
    $rows = dict_db()->query("SELECT level, COUNT(*) AS n FROM occupations GROUP BY level")->fetchAll(PDO::FETCH_ASSOC);
    $out = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    foreach ($rows as $r) $out[(int)$r['level']] = (int)$r['n'];
    return $out;
}
