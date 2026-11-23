<?php
// api/core/helpers/cache/cache_driver_helper.php - 缓存驱动连接测试
function cache_driver_test($driver, $params) {
    try {
        if ($driver === 'apcu') {
            if (!function_exists('apcu_store')) return array('ok' => false, 'msg' => 'APCu 扩展不可用');
            $k = 'his_cache_test_' . mt_rand();
            apcu_store($k, 1, 10);
            apcu_delete($k);
            return array('ok' => true, 'msg' => '');
        }
        if ($driver === 'redis') {
            if (!class_exists('Redis')) return array('ok' => false, 'msg' => 'Redis 扩展不可用');
            $r = new Redis();
            $r->connect($params['host'], (int)$params['port'], (float)$params['timeout']);
            if ($params['auth'] !== '') $r->auth($params['auth']);
            $k = 'his_cache_test_' . mt_rand();
            $r->setex($k, 10, '1');
            $r->del($k);
            return array('ok' => true, 'msg' => '');
        }
        if ($driver === 'memcached') {
            if (!class_exists('Memcached')) return array('ok' => false, 'msg' => 'Memcached 扩展不可用');
            $m = new Memcached();
            foreach (preg_split('/\s*,\s*/', trim($params['servers'])) as $one) {
                if ($one === '') continue;
                $parts = explode(':', $one, 2);
                $m->addServer(trim($parts[0]), isset($parts[1]) ? (int)$parts[1] : 11211);
            }
            if (!$m->getVersion()) return array('ok' => false, 'msg' => 'Memcached 连接失败');
            $k = 'his_cache_test_' . mt_rand();
            $m->set($k, 1, 10);
            $m->delete($k);
            return array('ok' => true, 'msg' => '');
        }
        return array('ok' => true, 'msg' => '');
    } catch (Exception $e) {
        return array('ok' => false, 'msg' => $e->getMessage());
    }
}
