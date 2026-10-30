<?php
// api/config.php

// ---------- 基础路径（其余配置依赖） ----------
define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');

// ---------- 基础设施配置库（config.db） ----------
// 数据库连接、全站时区等「最底层」配置独立于业务库存放（参考门诊一体化系统）；
// 未生成 config.db 时全部回退下方默认值，旧部署行为完全不变。
require_once __DIR__ . '/core/config_db.php';

// 全站时区：config.db（初始化向导设置）优先；缺省/非法回退 Asia/Shanghai
// （统一时区，避免 PHP 默认 UTC 导致时间相差 8 小时）
date_default_timezone_set(ConfigDB::timezone('Asia/Shanghai'));

define('APP_VERSION', '0.23.1');
define('ICD_DB_PATH', DATA_DIR . '/icd10.sqlite');

// ---------- 数据库驱动配置（config.db 优先，否则常量默认） ----------
$__dbType = strtolower(trim((string)ConfigDB::get('db.type', 'sqlite')));
if (!in_array($__dbType, array('sqlite', 'mysql', 'postgresql'), true)) $__dbType = 'sqlite';
$__dbPath = trim((string)ConfigDB::get('db.path', ''));
if ($__dbPath === '') $__dbPath = DATA_DIR . '/hospital.sqlite';
if (strpos($__dbPath, '/') !== 0) $__dbPath = DATA_DIR . '/' . $__dbPath;   // 相对路径按 data/ 展开
define('DB_PATH', $__dbPath);
define('DB_TYPE', $__dbType);
define('DB_DSN', 'sqlite:' . DB_PATH);
define('DB_HOST', (string)ConfigDB::get('db.host', '127.0.0.1'));
define('DB_NAME', (string)ConfigDB::get('db.name', 'his'));
define('DB_USER', (string)ConfigDB::get('db.user', 'root'));
define('DB_PASS', (string)ConfigDB::get('db.pass', ''));

// 会话 Cookie 策略
function isSecureRequest() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_VIA'])) return true;
    return false;
}

// 会话配置 (仅在 HTTP 请求上下文中)
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = isSecureRequest();
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', $isSecure ? 'None' : 'Lax');
        if ($isSecure) {
            ini_set('session.cookie_secure', 1);
        }
        ini_set('session.cookie_path', '/');
        // 会话有效期 12 小时 (PHP 默认 gc_maxlifetime 24 分钟过短, 长时间不操作即掉线 401)
        ini_set('session.gc_maxlifetime', 43200);
        session_name('HIS_SESSION');
    }
}
