<?php
// api/modules/admin/db_center.php - 数据库中心
// action=status|test|switch|tables|table-data
Auth::requireRole(['admin']);
$action = Validator::get('action', 'status');

if ($action === 'status' || $action === 'tables') {
    $pdo = DB::getPDO();
    $tables = [];
    if (DB_TYPE === 'sqlite') {
        $tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC), 'name');
    } else {
        $tables = array_column($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_ASSOC), 0);
    }
    Response::success([
        'driver' => DB_TYPE, 'path' => DB_PATH, 'host' => DB_HOST, 'dbname' => DB_NAME,
        'tables' => $tables, 'table_count' => count($tables),
    ]);
}

if ($action === 'test') {
    $driver = Validator::get('driver', 'sqlite');
    $testOk = false; $msg = '';
    try {
        if ($driver === 'sqlite') {
            $path = trim((string)Validator::get('path', ''));
            $p = new PDO('sqlite:' . ($path !== '' ? $path : DATA_DIR . '/hospital.sqlite'));
            $testOk = true;
        } elseif ($driver === 'mysql') {
            $p = new PDO('mysql:host=' . Validator::get('host', '127.0.0.1') . ';dbname=' . Validator::get('dbname', 'his') . ';charset=utf8mb4',
                Validator::get('user', 'root'), Validator::get('pass', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $testOk = true;
        } elseif ($driver === 'postgresql') {
            $p = new PDO('pgsql:host=' . Validator::get('host', '127.0.0.1') . ';dbname=' . Validator::get('dbname', 'his'),
                Validator::get('user', 'postgres'), Validator::get('pass', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $testOk = true;
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
    }
    Response::success(['ok' => $testOk, 'msg' => $msg], $testOk ? '连接成功' : '连接失败：' . $msg);
}

if ($action === 'switch') {
    $driver = Validator::get('driver', '');
    if (!in_array($driver, ['sqlite', 'mysql', 'postgresql'], true)) Response::error('无效的数据库驱动');
    if ($driver === 'sqlite') {
        ConfigDB::set('db.type', 'sqlite');
        ConfigDB::set('db.path', trim((string)Validator::get('path', '')));
    } else {
        ConfigDB::set('db.type', $driver);
        ConfigDB::set('db.host', trim((string)Validator::get('host', '127.0.0.1')));
        ConfigDB::set('db.name', trim((string)Validator::get('dbname', 'his')));
        ConfigDB::set('db.user', trim((string)Validator::get('user', 'root')));
        ConfigDB::set('db.pass', (string)Validator::get('pass', ''));
    }
    log_write('operation', 'config', '切换数据库驱动为 ' . $driver, '', 'warning', ['action' => 'db_switch']);
    Response::success([], '已切换数据库驱动（下次请求生效）');
}

if ($action === 'table-data') {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)Validator::get('table', ''));
    $limit = max(1, min(100, (int)Validator::get('limit', 20) ?: 20));
    $offset = max(0, (int)Validator::get('offset', 0));
    if ($table === '') Response::error('参数错误');
    $pdo = DB::getPDO();
    $rows = $pdo->query("SELECT * FROM \"$table\" LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
    $cols = [];
    if ($rows) $cols = array_keys($rows[0]);
    Response::success(['cols' => $cols, 'rows' => $rows]);
}
