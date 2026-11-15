<?php
// tools/build_dicts.php - 固定字典库构建工具 (一次性/数据更新时运行)
// 用法: php tools/build_dicts.php  (或 frankenphp php-cli tools/build_dicts.php)
// 输入: tools/dict_data/{regions,occupations,dicts}.json (打包种子, 数据不在 PHP 代码中)
// 输出: tools/dict_data/dicts.sqlite (固定字典库, 随仓库分发; 部署时复制到 data/dicts.sqlite)
// 说明: 造数逻辑仅存在于本工具; 运行时 (api/core) 只读取/复制该数据库, 不做数据构造。
$base = dirname(__DIR__);
$dir = __DIR__ . '/dict_data';
$outFile = $dir . '/dicts.sqlite';

function seed_read($dir, $name)
{
    $file = $dir . '/' . $name . '.json';
    if (!is_file($file)) { fwrite(STDERR, "缺少种子文件: $file\n"); exit(1); }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) { fwrite(STDERR, "种子解析失败: $file\n"); exit(1); }
    return $data;
}

@unlink($outFile);
@unlink($outFile . '-wal');
@unlink($outFile . '-shm');
$pdo = new PDO('sqlite:' . $outFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE regions (
    province TEXT NOT NULL, city TEXT NOT NULL, county TEXT NOT NULL,
    zip TEXT, code TEXT, PRIMARY KEY (province, city, county))");
$pdo->exec("CREATE TABLE occupations (
    code TEXT PRIMARY KEY, name TEXT NOT NULL, level INTEGER NOT NULL,
    parent_code TEXT DEFAULT '', path TEXT DEFAULT '', gbm TEXT DEFAULT '')");
$pdo->exec("CREATE TABLE dicts (
    dkey TEXT PRIMARY KEY, name TEXT NOT NULL, items TEXT NOT NULL,
    builtin INTEGER DEFAULT 0, updated_at TEXT, updated_by INTEGER)");

/* 省市县 + 邮编 */
$regions = seed_read($dir, 'regions');
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT OR IGNORE INTO regions (province, city, county, zip, code) VALUES (?,?,?,?,?)");
foreach ($regions as $r) $stmt->execute([$r['p'], $r['c'], $r['a'], (string)($r['z'] ?? ''), (string)($r['c2'] ?? '')]);
$pdo->commit();
echo 'regions: ' . count($regions) . "\n";

/* 职业分类大典 2022 (层级 + 路径) */
$packed = seed_read($dir, 'occupations');
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT OR REPLACE INTO occupations (code, name, level, parent_code, path, gbm) VALUES (?,?,?,?,?,?)");
$occN = 0;
foreach ($packed['categories'] as $cat) {
    $c1 = (string)$cat[0]; $n1 = (string)$cat[1];
    $stmt->execute([$c1, $n1, 1, '', $n1, '']); $occN++;
    foreach (($cat[2] ?? []) as $mid) {
        $c2 = (string)$mid[0]; $n2 = (string)$mid[1];
        $stmt->execute([$c2, $n2, 2, $c1, "$n1 > $n2", '']); $occN++;
        foreach (($mid[2] ?? []) as $minor) {
            $c3 = (string)$minor[0]; $n3 = (string)$minor[1];
            $stmt->execute([$c3, $n3, 3, $c2, "$n1 > $n2 > $n3", '']); $occN++;
            foreach (($minor[2] ?? []) as $occ) {
                $c4 = (string)$occ[0]; $n4 = (string)$occ[1];
                $stmt->execute([$c4, $n4, 4, $c3, "$n1 > $n2 > $n3 > $n4", '']); $occN++;
            }
        }
    }
}
$pdo->commit();
echo 'occupations: ' . $occN . "\n";

/* 院级字典 */
$dicts = seed_read($dir, 'dicts');
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT OR REPLACE INTO dicts (dkey, name, items, builtin, updated_at, updated_by) VALUES (?,?,?,1,?,NULL)");
$now = gmdate('Y-m-d H:i:s');
foreach ($dicts as $d) $stmt->execute([$d['key'], $d['name'], json_encode($d['items'], JSON_UNESCAPED_UNICODE), $now]);
$pdo->commit();
echo 'dicts: ' . count($dicts) . "\n";

$pdo->exec('VACUUM');
$pdo = null;
$size = round(filesize($outFile) / 1024, 1);
echo "构建完成: tools/dict_data/dicts.sqlite ({$size} KB)\n";
