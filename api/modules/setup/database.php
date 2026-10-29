<?php
// api/modules/setup/database.php - 向导第 2 步：数据库连接配置（测试通过后写入 config.db）
require_once __DIR__ . '/_common.php';
setup_require_open();
setup_require_stage(array('env', 'migrate'));   // 允许在数据库初始化前重配

$type = strtolower(trim((string)Validator::get('type', 'sqlite')));
if (!in_array($type, array('sqlite', 'mysql', 'postgresql'), true)) {
    Response::error('不支持的数据库类型：' . $type);
}
$param = array(
    'path' => trim((string)Validator::get('path', '')),
    'host' => trim((string)Validator::get('host', '')),
    'name' => trim((string)Validator::get('name', '')),
    'user' => trim((string)Validator::get('user', '')),
    'pass' => (string)Validator::get('pass', ''),
);
if ($type === 'sqlite') {
    if ($param['path'] === '') $param['path'] = DATA_DIR . '/hospital.sqlite';
} else {
    if ($param['host'] === '' || $param['name'] === '' || $param['user'] === '') {
        Response::error('请填写主机、数据库名与用户名');
    }
}

// ① 连接探测（含 SQLite 目录可写/建表探针）
list($ok, $msg) = setup_probe($type, $param);
if (!$ok) Response::error($msg, 422);

// ② 写入 config.db（数据库连接 + UTC 存储契约：全新安装直接 UTC）
$written = ConfigDB::setMany(array(
    'db.type' => $type,
    'db.path' => $param['path'],
    'db.host' => $param['host'],
    'db.name' => $param['name'],
    'db.user' => $param['user'],
    'db.pass' => $param['pass'],
    'time.storage' => 'utc',
    'setup.stage' => 'migrate',
));
if (!$written) Response::error('配置写入失败，请检查 data/ 目录权限', 500);

Response::success(array('message' => $msg, 'stage' => 'migrate'), '数据库配置已保存');
