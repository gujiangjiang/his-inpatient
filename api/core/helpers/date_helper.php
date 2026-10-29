<?php
// api/core/helpers/date_helper.php
// 时间获取与格式化统一委托 TimeHelper（UTC 存储 + 站点时区显示 + 对外 UTC 契约）。
// 依赖 api/core/helpers/time_helper.php 先加载。
class DateHelper {

    /** 当前存储时刻（utc 模式 = UTC；local 模式 = 站点墙钟，行为同旧版） */
    public static function now() {
        return TimeHelper::now();
    }

    /** 今日业务日（站点时区日期） */
    public static function today() {
        return TimeHelper::today();
    }

    /** 存储值 → 站点时区格式化输出（local 模式与旧版 strtotime+date 行为一致） */
    public static function format($datetime, $format = 'Y-m-d H:i') {
        if (!$datetime) return '';
        $t = TimeHelper::ts($datetime);
        return $t === null ? '' : date($format, $t);
    }
}
