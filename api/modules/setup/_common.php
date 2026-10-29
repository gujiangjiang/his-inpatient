<?php
// api/modules/setup/_common.php - 初始化向导公共辅助（仅被 setup/* 端点引用）
// 阶段（config.db setup.stage 断点续填）：env → migrate → site → admin → done

/** 当前向导阶段（已初始化返回 done） */
function setup_stage() {
    if (SetupGuard::check()) return 'done';
    if (!ConfigDB::exists()) return 'env';
    $stage = (string)ConfigDB::get('setup.stage', '');
    if (in_array($stage, array('migrate', 'site', 'admin', 'done'), true)) return $stage;
    return 'migrate';   // config.db 已存在但无阶段标记（异常中断）→ 从数据库初始化继续
}

/** 未初始化守卫（已初始化一律拒绝向导写操作） */
function setup_require_open() {
    if (SetupGuard::check()) Response::error('系统已初始化，向导已关闭', 403);
}

/** 阶段守卫：当前阶段不在允许集合内则拒绝（防跳步） */
function setup_require_stage(array $allow) {
    $stage = setup_stage();
    if (!in_array($stage, $allow, true)) {
        Response::error('初始化步骤顺序不正确（当前：' . $stage . '）', 409);
    }
}

/** 时区名校验（合法返回原名，非法返回 ''） */
function setup_valid_tz($tz) {
    $tz = trim((string)$tz);
    if ($tz === '') return '';
    try {
        new DateTimeZone($tz);
        return $tz;
    } catch (Exception $e) {
        return '';
    }
}

/** 用给定参数做连接探测（独立于 DB 单例，供向导保存前测试） */
function setup_probe($type, $p) {
    try {
        if ($type === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) return array(false, '缺少 pdo_sqlite 扩展');
            $path = trim((string)($p['path'] ?? ''));
            if ($path === '') $path = DATA_DIR . '/hospital.sqlite';
            if (strpos($path, '/') !== 0) $path = DATA_DIR . '/' . $path;
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return array(false, '目录不可创建：' . $dir);
            if (!is_writable($dir)) return array(false, '目录不可写：' . $dir);
            $pdo = new PDO('sqlite:' . $path, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $pdo->exec('CREATE TABLE IF NOT EXISTS _setup_probe (id INTEGER PRIMARY KEY)');
            $pdo->exec('DROP TABLE _setup_probe');
            return array(true, 'SQLite 连接正常：' . $path);
        }
        if ($type === 'mysql') {
            if (!extension_loaded('pdo_mysql')) return array(false, '缺少 pdo_mysql 扩展');
            $dsn = 'mysql:host=' . (string)($p['host'] ?? '') . ';dbname=' . (string)($p['name'] ?? '') . ';charset=utf8mb4';
            $pdo = new PDO($dsn, (string)($p['user'] ?? ''), (string)($p['pass'] ?? ''), array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5));
            $pdo->query('SELECT 1');
            return array(true, 'MySQL 连接正常');
        }
        if ($type === 'postgresql') {
            if (!extension_loaded('pdo_pgsql')) return array(false, '缺少 pdo_pgsql 扩展');
            $dsn = 'pgsql:host=' . (string)($p['host'] ?? '') . ';dbname=' . (string)($p['name'] ?? '');
            $pdo = new PDO($dsn, (string)($p['user'] ?? ''), (string)($p['pass'] ?? ''), array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5));
            $pdo->query('SELECT 1');
            return array(true, 'PostgreSQL 连接正常');
        }
    } catch (Exception $e) {
        return array(false, '连接失败：' . $e->getMessage());
    }
    return array(false, '不支持的数据库类型：' . $type);
}
