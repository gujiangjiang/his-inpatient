<?php
// api/core/helpers/string/string_helper.php
class StringHelper {
    public static function checkSessionId($id) {
        // 16-128 位安全字符
        return preg_match('/^[a-zA-Z0-9]{16,128}$/', $id);
    }
}
