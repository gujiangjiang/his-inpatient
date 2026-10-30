<?php
// api/scripts/tz_migrate.php - 存量库时间数据迁移（站点墙钟 → UTC）
/**
 * 三系统统一时间契约的存量库升级工具（与门诊一体化 tz_migrate 同设计）：
 *  - plan     只读预演：列出全部待转换 表.列 与行数、抽样转换前后对照
 *  - convert  执行「站点时区墙钟 → UTC」：SQLite 自动整库备份 → 逐列转换 →
 *             写入 JSON 清单（data/backups/）→ config.db time.storage 置 utc
 *  - rollback 按最近清单精确反向还原（UTC → 墙钟），并将 time.storage 回切 local
 *
 * 转换列识别：实库 PRAGMA 扫描（列名 _at/_time 结尾，含 ALTER 新增列）；
 *  - 排除清单：业务文本标签列（如 lab_catalog.report_time「30分钟」）
 *  - 值格式校验：仅转换形如「YYYY-MM-DD[ T]HH:MM」的时间戳值，其余原样跳过
 *    （清单记录 skipped 计数，保证不误伤）
 *  - 业务日期时间列（start_date 等）按本地墙钟业务语义存取，不在转换范围
 *
 * 用法：
 *   php api/scripts/tz_migrate.php plan
 *   php api/scripts/tz_migrate.php convert  --yes
 *   php api/scripts/tz_migrate.php rollback --yes
 *   （仅支持 SQLite 自动迁移；MySQL/PostgreSQL 请参照清单手工执行或 site 停机搬迁）
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/config_db.php';
require_once __DIR__ . '/../core/helpers/time_helper.php';

if (DB_TYPE !== 'sqlite') {
    exit("当前驱动为 " . DB_TYPE . "：本工具仅支持 SQLite 自动迁移（MySQL/PG 请手工 dump/迁移）\n");
}

$cmd = isset($argv[1]) ? strtolower(trim((string)$argv[1])) : 'plan';
$yes = in_array('--yes', $argv, true);
$dbArg = '';
foreach ($argv as $a) {
    if (strpos($a, '--db=') === 0) $dbArg = substr($a, strpos($a, '=') + 1);
}
if (!in_array($cmd, array('plan', 'convert', 'rollback'), true)) {
    exit("用法: tz_migrate.php plan|convert|rollback [--db=路径] [--yes] [--force]\n");
}

$tz = ConfigDB::timezone('Asia/Shanghai');
$offset = date('P');
$dbPath = $dbArg !== '' ? $dbArg : DB_PATH;
if (!is_file($dbPath)) exit("数据库文件不存在：{$dbPath}\n");
$pdo = ($dbArg !== '')
    ? new PDO('sqlite:' . $dbPath, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC))
    : DB::getPDO();
$backupDir = DATA_DIR . '/backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);

/** 排除清单：业务语义列（非存储时间戳——按本地墙钟存取，不参与 UTC 转换） */
$exclude = array(
    'lab_catalog.report_time' => true,        // 业务文本标签（如「30分钟」「当日16:00」）
    'consultations.planned_at' => true,       // 会诊计划时间：用户 datetime-local 选择的本地墙钟
);

/** 发现待转换列（实库 PRAGMA；_at/_time 结尾；排除清单外） */
function tz_columns($pdo, $exclude) {
    $out = array();
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        foreach ($pdo->query("PRAGMA table_info(\"" . $t . "\")")->fetchAll() as $c) {
            $col = $c['name'];
            if (!preg_match('/_at$|_time$/i', $col)) continue;
            if (isset($exclude[$t . '.' . $col])) continue;
            $out[$t][] = $col;
        }
    }
    return $out;
}

/** 值是否时间戳形态（防业务标签误伤） */
function tz_is_stamp($v) {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', trim((string)$v));
}

/** 墙钟（站点时区）→ UTC；非法返回 '' */
function tz_l2u($v) {
    if (!tz_is_stamp($v)) return '';
    $t = strtotime(trim((string)$v));
    return $t === false ? '' : gmdate('Y-m-d H:i:s', $t);
}

/** UTC → 墙钟（站点时区）；非法返回 '' */
function tz_u2l($v) {
    if (!tz_is_stamp($v)) return '';
    $t = strtotime(trim((string)$v) . ' UTC');
    return $t === false ? '' : date('Y-m-d H:i:s', $t);
}

$plan = tz_columns($pdo, $exclude);

/* ---------------- plan：只读预演 ---------------- */
if ($cmd === 'plan') {
    echo "【迁移预演 · 只读】 {DB_PATH}\n站点时区：{$tz}（{$offset}） 方向：墙钟 → UTC\n\n";
    $total = 0; $cols = 0;
    foreach ($plan as $t => $cs) {
        foreach ($cs as $c) {
            $rows = $pdo->query("SELECT \"" . $c . "\" AS v FROM \"" . $t . "\" WHERE \"" . $c . "\" IS NOT NULL AND \"" . $c . "\" != '' LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
            $n = 0; $samples = array();
            foreach ($rows as $v) {
                if (!tz_is_stamp($v)) continue;
                $n++;
                if (count($samples) < 3) $samples[] = $v . ' → ' . tz_l2u($v);
            }
            if ($n === 0) continue;
            $cols++; $total += $n;
            printf("%-40s %5d 行  例：%s\n", $t . '.' . $c, $n, implode('  |  ', $samples));
        }
    }
    echo "\n合计：{$cols} 列 / {$total} 个时间戳值待转换（预演不写入任何数据）\n";
    exit(0);
}

/* ---------------- rollback：按最近清单反向还原 + 模式回切 ---------------- */
if ($cmd === 'rollback') {
    if (!$yes) exit("回滚将还原全部已转换值并回切 time.storage=local，确认请加 --yes\n");
    $files = glob($backupDir . '/tz_migrate_*.json') ?: array();
    $target = null;
    foreach (array_reverse($files) as $f) {
        $j = json_decode((string)@file_get_contents($f), true);
        if (!is_array($j)) continue;
        if (isset($j['direction']) && $j['direction'] === 'to_utc'
            && isset($j['db']) && $j['db'] === $dbPath
            && (!isset($j['status']) || $j['status'] === 'applied')) {
            $target = array('file' => $f, 'manifest' => $j);
            break;
        }
    }
    if (!$target) exit("未找到 to_utc 迁移清单（data/backups/tz_migrate_*.json），无法回滚\n");
    $j = $target['manifest'];
    echo "【回滚】依据清单：" . basename($target['file']) . "（时区 {$j['tz']}）\n";
    $done = 0;
    $pdo->beginTransaction();
    foreach ((array)$j['tables'] as $t => $cs) {
        foreach ((array)$cs as $c => $n) {
            $st = $pdo->prepare("SELECT rowid AS rid, \"" . $c . "\" AS v FROM \"" . $t . "\" WHERE \"" . $c . "\" IS NOT NULL AND \"" . $c . "\" != ''");
            $st->execute();
            $up = $pdo->prepare("UPDATE \"" . $t . "\" SET \"" . $c . "\"=? WHERE rowid=?");
            foreach ($st->fetchAll() as $r) {
                $back = tz_u2l($r['v']);
                if ($back !== '' && $back !== $r['v']) { $up->execute(array($back, $r['rid'])); $done++; }
            }
        }
    }
    $pdo->commit();
    $j['status'] = 'rolled_back';
    $j['rolled_back_at'] = gmdate('Y-m-d H:i:s');
    file_put_contents($target['file'], json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if ($dbArg === '') {
        ConfigDB::set('time.storage', 'local');
        echo "回滚完成：还原 {$done} 个值；time.storage 已回切 local（重复执行安全：清单已标记 rolled_back）\n";
    } else {
        echo "回滚完成（副本演练）：还原 {$done} 个值；未改动实际 time.storage\n";
    }
    exit(0);
}

/* ---------------- convert：执行 墙钟 → UTC ---------------- */
if ($cmd === 'convert') {
    if (!$yes) exit("convert 将改写全库时间数据并切换存储模式，确认请加 --yes\n");
    // 防重复转换守卫（重复转换 = 已 UTC 数据再减时区偏移 → 8 小时数据损坏）：
    //   ① 主库（未指定 --db）且 time.storage 已是 utc；或
    //   ② 该库路径已有 status=applied 的 to_utc 迁移清单 → 拒绝（--force 显式覆盖）
    if (!in_array('--force', $argv, true)) {
        if ($dbArg === '' && (string)ConfigDB::get('time.storage', 'local') === 'utc') {
            fwrite(STDERR, "拒绝执行：该库已完成 UTC 转换（time.storage=utc）。\n重复 convert 会造成时区偏移损坏；如需重跑请先 rollback 还原，或显式加 --force\n");
            exit(2);
        }
        foreach (glob($backupDir . '/tz_migrate_*.json') ?: array() as $f) {
            $j = json_decode((string)@file_get_contents($f), true);
            if (is_array($j) && isset($j['direction']) && isset($j['db']) && isset($j['status'])
                && $j['direction'] === 'to_utc' && $j['db'] === $dbPath && $j['status'] === 'applied') {
                fwrite(STDERR, "拒绝执行：该库已存在已应用的 to_utc 迁移清单（" . basename($f) . "）。\n重复 convert 会造成时区偏移损坏；如需重跑请先 rollback 还原，或显式加 --force\n");
                exit(2);
            }
        }
    }
    // 1) 整库备份
    $backup = $backupDir . '/hospital_' . gmdate('YmdHis') . '.db';
    if (!@copy($dbPath, $backup)) exit("备份失败（{$backup}），已中止\n");
    echo "【执行】墙钟 → UTC：{$dbPath}\n时区：{$tz}（{$offset}）  备份：{$backup}\n";
    $manifest = array(
        'db' => $dbPath, 'direction' => 'to_utc', 'tz' => $tz, 'tz_offset' => $offset,
        'started_at' => gmdate('Y-m-d H:i:s'), 'backup' => $backup,
        'tables' => array(), 'skipped' => 0, 'status' => 'applied',
    );
    $total = 0;
    foreach ($plan as $t => $cs) {
        foreach ($cs as $c) {
            $st = $pdo->prepare("SELECT rowid AS rid, \"" . $c . "\" AS v FROM \"" . $t . "\" WHERE \"" . $c . "\" IS NOT NULL AND \"" . $c . "\" != ''");
            $st->execute();
            $rows = $st->fetchAll();
            if (!$rows) continue;
            $pdo->beginTransaction();
            $up = $pdo->prepare("UPDATE \"" . $t . "\" SET \"" . $c . "\"=? WHERE rowid=?");
            $cnt = 0;
            foreach ($rows as $r) {
                $utc = tz_l2u($r['v']);
                if ($utc === '') { $manifest['skipped']++; continue; }
                $up->execute(array($utc, $r['rid']));
                $cnt++;
            }
            $pdo->commit();
            if ($cnt > 0) {
                $manifest['tables'][$t][$c] = $cnt;
                $total += $cnt;
                printf("  %-40s %5d\n", $t . '.' . $c, $cnt);
            }
        }
    }
    $manifest['finished_at'] = gmdate('Y-m-d H:i:s');
    $logFile = $backupDir . '/tz_migrate_' . gmdate('YmdHis') . '.json';
    file_put_contents($logFile, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // 2) 存储模式切换（数据与模式一步同步；副本演练不切换实际模式）
    echo "\n完成：{$total} 个值已转为 UTC（跳过非时间戳值 {$manifest['skipped']} 个）；清单：{$logFile}\n";
    if ($dbArg === '') {
        ConfigDB::set('time.storage', 'utc');
        echo "time.storage 已置为 utc；回退方式：php api/scripts/tz_migrate.php rollback --yes\n";
    } else {
        echo "副本演练：未改动实际 time.storage\n";
    }
    exit(0);
}
