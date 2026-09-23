<?php
// api/core/helpers/date_helper.php
class DateHelper {
    public static function now() {
        return date('Y-m-d H:i:s');
    }

    public static function today() {
        return date('Y-m-d');
    }

    public static function format($datetime, $format = 'Y-m-d H:i') {
        if (!$datetime) return '';
        $ts = strtotime($datetime);
        return $ts ? date($format, $ts) : '';
    }
}
