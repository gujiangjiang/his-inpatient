<?php
// api/modules/setup/cache.php - 向导第 4 步：缓存驱动（默认 File，可选 apcu/redis/memcached）
require_once __DIR__ . '/_common.php';
setup_require_open();
setup_require_stage(array('migrate', 'site'));   // 允许在站点信息前重配

$driver = strtolower(trim((string)Validator::get('driver', 'file')));
$opt = ConfigDB::driverOptions();
$cacheOpts = isset($opt['cache']) ? $opt['cache'] : array();
if (!isset($cacheOpts[$driver])) Response::error('无效的缓存驱动');

// 扩展校验
$needExt = isset($cacheOpts[$driver]['extension']) ? $cacheOpts[$driver]['extension'] : '';
if ($needExt !== '' && !extension_loaded($needExt)) {
    Response::error('当前 PHP 未安装 ' . strtoupper($needExt) . ' 扩展，无法使用该缓存');
}

// 写 config.db
$pairs = array('cache.driver' => $driver, 'setup.stage' => 'site');
if ($driver === 'redis') {
    $pairs['cache.redis.host'] = trim((string)Validator::get('host', '127.0.0.1'));
    $pairs['cache.redis.port'] = (string)(int)Validator::get('port', 6379);
    $pairs['cache.redis.auth'] = (string)Validator::get('auth', '');
    $pairs['cache.redis.prefix'] = trim((string)Validator::get('prefix', 'his_sess:'));
    $pairs['cache.redis.timeout'] = (string)(float)Validator::get('timeout', 2.0);
} elseif ($driver === 'memcached') {
    $pairs['cache.memcached.servers'] = trim((string)Validator::get('servers', '127.0.0.1:11211'));
    $pairs['cache.memcached.prefix'] = trim((string)Validator::get('prefix', 'his_sess:'));
}
ConfigDB::setMany($pairs);

Response::success(array('driver' => $driver, 'stage' => 'site'), '缓存配置已保存');
