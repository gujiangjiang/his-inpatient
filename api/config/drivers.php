<?php
// api/config/drivers.php - 驱动注册表（缓存驱动唯一数据源）
// 缓存中心前端下拉与后端校验共用；新增驱动只维护本文件。
return array(
    'cache' => array(
        'file' => array('label' => 'File（本地文件，零依赖）', 'extension' => ''),
        'apcu' => array('label' => 'APCu（内存）', 'extension' => 'apcu'),
        'redis' => array('label' => 'Redis', 'extension' => 'redis', 'params' => array(
            'host' => array('key' => 'cache.redis.host', 'label' => '主机', 'default' => '127.0.0.1'),
            'port' => array('key' => 'cache.redis.port', 'label' => '端口', 'default' => '6379'),
            'auth' => array('key' => 'cache.redis.auth', 'label' => '密码（可选）', 'default' => ''),
            'prefix' => array('key' => 'cache.redis.prefix', 'label' => '键前缀', 'default' => 'his_sess:'),
            'timeout' => array('key' => 'cache.redis.timeout', 'label' => '超时（秒）', 'default' => '2.0'),
        )),
        'memcached' => array('label' => 'Memcached', 'extension' => 'memcached', 'params' => array(
            'servers' => array('key' => 'cache.memcached.servers', 'label' => '服务器（逗号分隔 host:port）', 'default' => '127.0.0.1:11211'),
            'prefix' => array('key' => 'cache.memcached.prefix', 'label' => '键前缀', 'default' => 'his_sess:'),
        )),
    ),
);
