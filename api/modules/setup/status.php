<?php
// api/modules/setup/status.php - 初始化向导状态（环境检测 / 阶段 / 续填信息）
require_once __DIR__ . '/_common.php';

$stage = setup_stage();
$env = array(
    'php' => array(
        'ok' => version_compare(PHP_VERSION, '7.0.0', '>='),
        'version' => PHP_VERSION,
        'required' => 'PHP 7.0+',
    ),
    'pdo' => array(
        'ok' => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql'),
        'drivers' => array_values(array_filter(array(
            extension_loaded('pdo_sqlite') ? 'sqlite' : null,
            extension_loaded('pdo_mysql') ? 'mysql' : null,
            extension_loaded('pdo_pgsql') ? 'postgresql' : null,
        ))),
    ),
    'data' => array(
        'ok' => is_dir(DATA_DIR) ? is_writable(DATA_DIR) : @mkdir(DATA_DIR, 0775, true),
        'path' => DATA_DIR,
    ),
);

// 数据库连通性（config.db 已配置时用其参数探测；未配置时不触碰 DB 单例）
$db = array('configured' => ConfigDB::exists() && (string)ConfigDB::get('db.type', '') !== '', 'type' => (string)ConfigDB::get('db.type', 'sqlite'), 'ok' => null, 'message' => '');
if ($db['configured']) {
    list($ok, $msg) = setup_probe($db['type'], array(
        'path' => ConfigDB::get('db.path', ''),
        'host' => ConfigDB::get('db.host', ''),
        'name' => ConfigDB::get('db.name', ''),
        'user' => ConfigDB::get('db.user', ''),
        'pass' => ConfigDB::get('db.pass', ''),
    ));
    $db['ok'] = $ok;
    $db['message'] = $msg;
}

// 站点信息回显（可达时读业务库；失败静默）
$site = array('hospital_name' => '', 'hospital_code' => '', 'timezone' => ConfigDB::timezone('Asia/Shanghai'));
if ($stage === 'admin' || $stage === 'done' || $stage === 'site') {
    try {
        $row = DB::selectOne("SELECT config_value FROM system_config WHERE config_key='hospital_name'");
        if ($row) $site['hospital_name'] = (string)$row['config_value'];
        $row = DB::selectOne("SELECT config_value FROM system_config WHERE config_key='hospital_code'");
        if ($row) $site['hospital_code'] = (string)$row['config_value'];
    } catch (Exception $e) { /* 忽略 */ }
}

Response::success(array(
    'setup_completed' => SetupGuard::check(),
    'stage' => $stage,
    'env' => $env,
    'db' => $db,
    'site' => $site,
    'default_sqlite_path' => DATA_DIR . '/hospital.sqlite',
    'timezones' => DateTimeZone::listIdentifiers(),
));
