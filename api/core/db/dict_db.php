<?php
// api/core/db/dict_db.php - 独立基础字典数据库 (data/dicts.sqlite, 与主业务库/配置库分离)
// 内容: 省市县+邮编 (regions) / 职业分类大典2022 (occupations) / 基础字典 (dicts)
// 数据: **固定字典库即 data/dicts.sqlite 本身, 随仓库提交远程** (tools/dict_data/*.json
//       为种子, tools/build_dicts.php 为构建工具 — 数据与造数逻辑均在 tools/)
// 决策: 字典体量大且相对静态 — 独立库便于主库瘦身、单独更新/备份、与业务库解耦。
// 升级路径: 迁移 057 将旧 emr_dicts (主库) 自定义键合并迁移后移除旧表 (固定库随代码一起更新)。

/** 字典库路径 (固定库与运行库为同一文件, 随仓库分发) */
function dict_db_path()
{
    return DATA_DIR . '/dicts.sqlite';
}

/** 字典库 PDO */
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

/** 种子 JSON 读取 (只读回退用: 库不可用时的院级字典默认值; 数据位于 tools/) */
function dict_seed_json($name)
{
    $file = BASE_DIR . '/tools/dict_data/' . $name . '.json';
    if (!is_file($file)) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

/** 建表 (空库兜底; 固定库复制时已含结构与数据, 本函数幂等) */
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

/** 确保结构可用 (固定库随仓库提供; 数据缺失时记日志提示用构建工具重建, core 不做造数) */
function dict_db_ensure()
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $wasMissing = !is_file(dict_db_path());
        dict_db_schema();
        if ($wasMissing || (int)dict_db()->query('SELECT COUNT(*) FROM regions')->fetchColumn() === 0) {
            error_log('[dict_db] 字典数据缺失 (data/dicts.sqlite); 请运行 php tools/build_dicts.php 重建');
        }
    } catch (Exception $e) {
        error_log('[dict_db] ensure 失败: ' . $e->getMessage());
    }
}

/** 省市县级联数据: 嵌套 {provinces:[{name, cities:[{name, areas:[[county, zip, code]]}]}]} */
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
        if (!isset($idx[$p . '|' . $r['city']])) {
            $idx[$p . '|' . $r['city']] = count($out[$pi]['cities']);
            $out[$pi]['cities'][] = ['name' => $r['city'], 'areas' => []];
        }
        $ci = $idx[$p . '|' . $r['city']];
        $out[$pi]['cities'][$ci]['areas'][] = [$r['county'], (string)$r['zip'], (string)$r['code']];
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
