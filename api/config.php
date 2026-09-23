<?php
// api/config.php
define('APP_VERSION', '0.11.0');
define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . '/data');
define('DB_PATH', DATA_DIR . '/hospital.sqlite');
define('ICD_DB_PATH', DATA_DIR . '/icd10.sqlite');

// 数据库驱动配置
define('DB_TYPE', 'sqlite');
define('DB_DSN', 'sqlite:' . DB_PATH);
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'his');
define('DB_USER', 'root');
define('DB_PASS', '');

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
        session_name('HIS_SESSION');
    }
}
