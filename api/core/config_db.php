<?php
// api/core/config_db.php - 独立基础设施配置库（data/config.db，SQLite）
// 数据库连接、时区等「最底层」配置独立于业务库存放，
// 删除 config.db 仅重置基础设施配置、不破坏业务数据（可重新初始化向导绑定）。
// 未生成 config.db 时按 api/config.php 常量默认运行（旧版向后兼容，行为不变）。

class ConfigDB {

    /** 基础设施配置键（唯一数据源；新增键只在此登记说明） */
    // db.type        sqlite|mysql|postgresql   （默认 sqlite）
    // db.path        sqlite 数据文件路径        （默认 DATA_DIR/hospital.sqlite）
    // db.host/db.name/db.user/db.pass           mysql/postgresql 连接参数
    // timezone       全站时区（如 Asia/Shanghai）——初始化向导设置
    // setup.stage    初始化向导进度标记（多步向导断点续填）

    private static $pdo = null;
    private static $cache = null;
    private static $loaded = false;

    /** config.db 文件路径 */
    public static function path() {
        return DATA_DIR . '/config.db';
    }

    /** 是否已存在（已初始化过基础设施配置） */
    public static function exists() {
        return is_file(self::path());
    }

    /**
     * 打开 config.db（严格校验 SQLite Magic Header）。
     * 损坏文件自动备份为 config.db.corrupt.[时间戳] 并优雅降级（返回 null，
     * 调用方回退默认配置），严禁抛 500。
     */
    private static function pdo() {
        if (self::$pdo instanceof PDO) return self::$pdo;
        $file = self::path();
        if (!is_file($file)) return null;
        $head = @file_get_contents($file, false, null, 0, 16);
        if ($head !== "SQLite format 3\0") {
            @rename($file, $file . '.corrupt.' . date('YmdHis'));
            error_log('[ConfigDB] config.db 头校验失败，已备份为 .corrupt 并回退默认配置');
            return null;
        }
        try {
            $pdo = new PDO('sqlite:' . $file, null, null, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (Exception $e) {
            error_log('[ConfigDB] config.db 打开失败（回退默认配置）：' . $e->getMessage());
            return null;
        }
        self::$pdo = $pdo;
        return $pdo;
    }

    /** 建表（幂等） */
    private static function ensureTable(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS config (
            ckey       TEXT PRIMARY KEY,
            cvalue     TEXT DEFAULT '',
            updated_at TEXT DEFAULT ''
        )");
    }

    /** 读取全部配置（缓存；未初始化/损坏 → 空数组） */
    private static function load($force = false) {
        if (self::$loaded && !$force) return;
        self::$loaded = true;
        self::$cache = array();
        $pdo = self::pdo();
        if (!$pdo) return;
        try {
            foreach ($pdo->query("SELECT ckey, cvalue FROM config")->fetchAll() as $r) {
                self::$cache[(string)$r['ckey']] = (string)$r['cvalue'];
            }
        } catch (Exception $e) {
            self::$cache = array();   // 表不存在等：视为空
        }
    }

    /** 读单键（缺省返回 $default） */
    public static function get($key, $default = '') {
        self::load();
        return array_key_exists($key, self::$cache) ? self::$cache[$key] : $default;
    }

    /** 全部键值（引导早期使用：一次性应用覆盖默认常量） */
    public static function all() {
        self::load();
        return self::$cache;
    }

    /**
     * 写单键（首次写入自动创建 config.db 与表）。
     * @return bool 写入成功
     */
    public static function set($key, $value) {
        $pdo = self::pdo();
        if (!$pdo) {
            // 首次创建：PDO sqlite 打开不存在的文件会自动建库
            if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
            try {
                $pdo = new PDO('sqlite:' . self::path(), null, null, array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ));
                $pdo->exec('PRAGMA busy_timeout = 5000');
                self::$pdo = $pdo;
            } catch (Exception $e) {
                error_log('[ConfigDB] config.db 创建失败：' . $e->getMessage());
                return false;
            }
        }
        try {
            self::ensureTable($pdo);
            $st = $pdo->prepare("INSERT OR REPLACE INTO config(ckey,cvalue,updated_at) VALUES(?,?,?)");
            $st->execute(array((string)$key, (string)$value, gmdate('Y-m-d H:i:s')));
            self::load(true);
            return true;
        } catch (Exception $e) {
            error_log('[ConfigDB] 写入失败（' . $key . '）：' . $e->getMessage());
            return false;
        }
    }

    /** 批量写（向导分步保存用） */
    public static function setMany(array $pairs) {
        $ok = true;
        foreach ($pairs as $k => $v) {
            if (!self::set($k, $v)) $ok = false;
        }
        return $ok;
    }

    /** 删除单键（重新初始化时清理用） */
    public static function remove($key) {
        $pdo = self::pdo();
        if (!$pdo) return true;
        try {
            $st = $pdo->prepare("DELETE FROM config WHERE ckey=?");
            $st->execute(array((string)$key));
            self::load(true);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 时区解析（初始化向导与引导共用）：config.db timezone 优先，
     * 非法/缺失回退 $fallback；返回经 DateTimeZone 校验的合法时区名。
     */
    public static function timezone($fallback = 'Asia/Shanghai') {
        $tz = trim((string)self::get('timezone', ''));
        if ($tz === '') return $fallback;
        try {
            new DateTimeZone($tz);
            return $tz;
        } catch (Exception $e) {
            return $fallback;
        }
    }

    /* ==================== 缓存驱动 ==================== */

    /** 当前缓存驱动（config.db cache.driver，缺省 file） */
    public static function cacheDriver() {
        $d = trim((string)self::get('cache.driver', 'file'));
        return in_array($d, array('file', 'apcu', 'redis', 'memcached'), true) ? $d : 'file';
    }

    /** Redis 连接参数（config.db cache.redis.*，缺省默认） */
    public static function redisParams() {
        return array(
            'host' => (string)self::get('cache.redis.host', '127.0.0.1'),
            'port' => (int)self::get('cache.redis.port', 6379),
            'auth' => (string)self::get('cache.redis.auth', ''),
            'prefix' => (string)self::get('cache.redis.prefix', 'his_sess:'),
            'timeout' => (float)self::get('cache.redis.timeout', 2.0),
        );
    }

    /** Memcached 连接参数（config.db cache.memcached.*，缺省默认） */
    public static function memcachedParams() {
        return array(
            'servers' => (string)self::get('cache.memcached.servers', '127.0.0.1:11211'),
            'prefix' => (string)self::get('cache.memcached.prefix', 'his_sess:'),
        );
    }

    /** 驱动注册表（drivers.php 唯一数据源；供缓存中心前端渲染） */
    public static function driverOptions() {
        $f = BASE_DIR . '/api/config/drivers.php';
        if (is_file($f)) {
            $opt = include $f;
            return is_array($opt) ? $opt : array();
        }
        return array();
    }
}
