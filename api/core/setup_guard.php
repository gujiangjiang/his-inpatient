<?php
// api/core/setup_guard.php - 未初始化时阻断业务接口
class SetupGuard {
    private static $checked = false;
    private static $isSetup = false;

    public static function check() {
        if (self::$checked) return self::$isSetup;
        self::$checked = true;
        try {
            $config = DB::selectOne('SELECT config_value FROM system_config WHERE config_key = ?', ['setup_completed']);
            self::$isSetup = $config ? ($config['config_value'] === '1') : false;
        } catch (PDOException $e) {
            self::$isSetup = false;
        }
        return self::$isSetup;
    }

    public static function guard() {
        $isSetup = self::check();
        if (!$isSetup) {
            $path = trim($_SERVER['REQUEST_URI'], '/');
            $path = preg_replace('#\?.*$#', '', $path);
            // 允许初始化相关接口
            $allowed = ['auth/setup-status', 'auth/setup', 'auth/login'];
            $isAllowed = false;
            foreach ($allowed as $a) {
                if (strpos($path, 'api/' . $a) === 0) {
                    $isAllowed = true;
                    break;
                }
            }
            if (!$isAllowed) {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'error',
                    'message' => '系统未初始化',
                    'data' => null,
                    'code' => 'SETUP_REQUIRED'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}
