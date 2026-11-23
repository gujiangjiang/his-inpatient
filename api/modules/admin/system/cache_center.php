<?php
// api/modules/admin/system/cache_center.php - 缓存与性能
// action=status|flush|driver-save
Auth::requireRole(['admin']);
$action = Validator::get('action', 'status');

if ($action === 'status') {
    $st = Cache::stats();
    $st['drivers'] = ConfigDB::driverOptions();
    Response::success($st);
}

if ($action === 'flush') {
    $scope = Validator::get('scope', 'all');
    $map = array('config' => 'cfg_', 'dict' => 'dict_', 'call' => 'call_', 'all' => '');
    $prefix = isset($map[$scope]) ? $map[$scope] : '';
    $count = Cache::flush($prefix);
    log_write('operation', 'config', '刷新缓存：' . ($scope === 'all' ? '全部' : $scope) . ' ' . $count . ' 键', '', 'info', ['action' => 'cache_flush']);
    Response::success(array('scope' => $scope, 'count' => $count), '已刷新缓存 ' . $count . ' 个键');
}

if ($action === 'driver-save') {
    $driver = Validator::get('driver', '');
    $opt = ConfigDB::driverOptions();
    $cacheOpts = isset($opt['cache']) ? $opt['cache'] : array();
    if (!isset($cacheOpts[$driver])) Response::error('无效的缓存驱动');
    // 扩展校验
    $needExt = isset($cacheOpts[$driver]['extension']) ? $cacheOpts[$driver]['extension'] : '';
    if ($needExt !== '' && !extension_loaded($needExt)) {
        Response::error('当前 PHP 未安装 ' . strtoupper($needExt) . ' 扩展，无法使用该缓存');
    }
    // 保存前连接测试（redis/memcached）
    if ($driver === 'redis' || $driver === 'memcached') {
        $testParams = array(
            'host' => (string)Validator::get('host', '127.0.0.1'),
            'port' => (int)Validator::get('port', 6379),
            'auth' => (string)Validator::get('auth', ''),
            'prefix' => (string)Validator::get('prefix', 'his_sess:'),
            'timeout' => (float)Validator::get('timeout', 2.0),
            'servers' => (string)Validator::get('servers', '127.0.0.1:11211'),
        );
        $ok = cache_driver_test($driver, $testParams);
        if (!$ok['ok']) Response::error('缓存驱动不可用，已拒绝保存：' . $ok['msg']);
    }
    ConfigDB::set('cache.driver', $driver);
    if ($driver === 'redis') {
        ConfigDB::set('cache.redis.host', (string)Validator::get('host', '127.0.0.1'));
        ConfigDB::set('cache.redis.port', (string)(int)Validator::get('port', 6379));
        ConfigDB::set('cache.redis.auth', (string)Validator::get('auth', ''));
        ConfigDB::set('cache.redis.prefix', (string)Validator::get('prefix', 'his_sess:'));
        ConfigDB::set('cache.redis.timeout', (string)(float)Validator::get('timeout', 2.0));
    } elseif ($driver === 'memcached') {
        ConfigDB::set('cache.memcached.servers', (string)Validator::get('servers', '127.0.0.1:11211'));
        ConfigDB::set('cache.memcached.prefix', (string)Validator::get('prefix', 'his_sess:'));
    }
    log_write('operation', 'config', '切换缓存驱动为 ' . strtoupper($driver), '', 'warning', ['action' => 'cache_driver_save']);
    Response::success(array('driver' => $driver), '缓存驱动已切换为 ' . strtoupper($driver));
}
