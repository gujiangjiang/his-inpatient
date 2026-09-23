<?php
// api/core/validator.php - 输入校验，支持 JSON 请求体
class Validator {
    private static $data = null;

    public static function init() {
        if (self::$data !== null) return;
        $input = file_get_contents('php://input');
        $json = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            self::$data = $json;
        } else {
            self::$data = array_merge($_GET, $_POST);
        }
    }

    public static function all() {
        self::init();
        return self::$data;
    }

    public static function get($key, $default = null) {
        self::init();
        return isset(self::$data[$key]) ? self::$data[$key] : $default;
    }

    public static function require($keys, $data = null) {
        if ($data === null) {
            $data = self::all();
        }
        $missing = [];
        foreach ($keys as $key) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
                $missing[] = $key;
            }
        }
        if (!empty($missing)) {
            Response::error('缺少参数：' . implode(', ', $missing));
        }
        return true;
    }
}
