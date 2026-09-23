<?php
// api/core/helpers/string_helper.php
class StringHelper {
    public static function escape($str) {
        if ($str === null) return '';
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function random($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    public static function checkSessionId($id) {
        // 16-128 位安全字符
        return preg_match('/^[a-zA-Z0-9]{16,128}$/', $id);
    }
}
