<?php
// api/core/Cache.php - 通用缓存（参照门诊一体化，file 驱动）
// 用法：Cache::get($key) / set($key,$value,$ttl) / delete($key) / flush() / stats()
// 存储：DATA_DIR/cache/{md5(key)}.php（serialize 数组，含 expire）；TTL 秒，0=永久。
class Cache {
    private static $dir = null;

    private static function dir() {
        if (self::$dir !== null) return self::$dir;
        self::$dir = DATA_DIR . '/cache';
        if (!is_dir(self::$dir)) @mkdir(self::$dir, 0775, true);
        return self::$dir;
    }

    private static function file($key) {
        return self::dir() . '/' . md5((string)$key) . '.php';
    }

    /** 读缓存；未命中/过期返回 null */
    public static function get($key) {
        $file = self::file($key);
        if (!is_file($file)) return null;
        $data = @file_get_contents($file);
        if ($data === false) return null;
        $arr = @unserialize($data);
        if (!is_array($arr) || !isset($arr['expire'], $arr['value'])) return null;
        if ($arr['expire'] > 0 && time() >= $arr['expire']) {
            @unlink($file);
            return null;
        }
        return $arr['value'];
    }

    /** 写缓存；$ttl 秒，0=永久 */
    public static function set($key, $value, $ttl = 0) {
        $expire = $ttl > 0 ? time() + (int)$ttl : 0;
        $content = serialize(array('expire' => $expire, 'value' => $value));
        $file = self::file($key);
        return @file_put_contents($file, $content, LOCK_EX) !== false;
    }

    /** 删除单键 */
    public static function delete($key) {
        $file = self::file($key);
        if (is_file($file)) @unlink($file);
        return true;
    }

    /** 全部清空 */
    public static function flush() {
        foreach (glob(self::dir() . '/*.php') ?: array() as $f) @unlink($f);
        return true;
    }

    /** 缓存统计（条目数/占用） */
    public static function stats() {
        $files = glob(self::dir() . '/*.php') ?: array();
        $bytes = 0;
        foreach ($files as $f) {
            if (is_file($f)) $bytes += filesize($f);
        }
        return array('dir' => self::dir(), 'count' => count($files), 'bytes' => $bytes);
    }
}
