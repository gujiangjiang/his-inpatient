<?php
// api/core/helpers/time_helper.php - 时间与时区统一助手
/**
 * 三系统统一时间契约（HIS / 门诊一体化 / PACS）：
 *  - 存储：所有 DATETIME 列存 UTC（'Y-m-d H:i:s'），写入唯一来源 now()；
 *  - 显示：全站页面与内部 API 载荷经 local() / localize() 换算为站点时区
 *    （config.db timezone，初始化向导设置）；
 *  - 对外接口：isoUtc() 输出 UTC（ISO 8601 带 Z），不受站点时区影响；
 *    入站外部时间经 fromUtc() 按 UTC 解释落库；
 *  - 业务日期（*_date 等）：站点时区日期（分日归属按站点时区），today() 即本地日。
 *
 * 双模式过渡（存量库平滑迁移，与门诊一体化一致）：
 *   config.db `time.storage` = 'utc'（新装向导写入）/ 'local'（存量库默认，
 *   由迁移脚本 convert 后置 'utc'）；local 模式下本文件全部行为与旧版一致。
 */
class TimeHelper {

    /** 是否 UTC 存储模式（config.db 运行时读取——向导同请求内写入即时生效） */
    public static function storageUtc() {
        return (string)ConfigDB::get('time.storage', 'local') === 'utc';
    }

    /** 当前模式名（'utc' | 'local'，日志与自检用） */
    public static function modeName() {
        return self::storageUtc() ? 'utc' : 'local';
    }

    /* ==================== 写入（存储值唯一来源） ==================== */

    /** 当前存储时刻（utc 模式 = UTC 串；local 模式 = 站点墙钟串，行为同旧版） */
    public static function now() {
        return self::storageUtc() ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }

    /** 今日业务日（站点时区日期，两模式一致） */
    public static function today() {
        return date('Y-m-d');
    }

    /**
     * 站点时区当前墙钟串（业务日期时间字段专用：长期医嘱起止、会诊开始等）。
     * 这类字段按「本地墙钟」业务语义存取，不随 UTC 存储模式换算——
     * 与 * _at 存储时间列严格区分（详见各写入点注释）。
     */
    public static function nowLocal() {
        return date('Y-m-d H:i:s');
    }

    /* ==================== 解析与换算 ==================== */

    /** 存储串 → epoch（模式感知）；空/非法返回 null */
    public static function ts($v) {
        $s = trim((string)$v);
        if ($s === '') return null;
        $t = self::storageUtc() ? strtotime($s . ' UTC') : strtotime($s);
        return $t === false ? null : $t;
    }

    /**
     * 存储串 → 站点时区显示串（展示/内部 API 唯一入口）。
     * local 模式恒等返回（老行为逐字节不变）；utc 模式按站点时区换算。
     */
    public static function local($v, $fmt = null) {
        $s = (string)$v;
        if ($s === '' || !self::storageUtc()) return $s;
        $t = self::ts($s);
        if ($t === null) return $s;
        return date(($fmt !== null && $fmt !== '') ? $fmt : 'Y-m-d H:i:s', $t);
    }

    /** 存储串 → 站点时区业务日 'Y-m-d'（用于业务日字段/分日展示） */
    public static function localDate($v) {
        $s = (string)$v;
        if ($s === '') return '';
        if (!self::storageUtc()) return substr($s, 0, 10);
        $t = self::ts($s);
        return $t === null ? '' : date('Y-m-d', $t);
    }

    /** 站点时区墙钟串 → 存储值（外部墙钟入站/本地日边界共用） */
    public static function fromLocal($v) {
        $s = trim((string)$v);
        if ($s === '') return '';
        if (!self::storageUtc()) return $s;
        $t = strtotime($s);
        return $t === false ? '' : gmdate('Y-m-d H:i:s', $t);
    }

    /**
     * 站点业务日 [start, end) 的存储边界串（分日窗口查询统一用）：
     * 查询写法 col >= ? AND col < ?（两模式通用，替代 date(col)=? 函数比较）。
     * @return array [start, end]
     */
    public static function dayRange($ymd) {
        $ymd = trim((string)$ymd);
        if ($ymd === '') return array('', '');
        $next = date('Y-m-d', strtotime($ymd . ' +1 day'));
        return array(self::fromLocal($ymd . ' 00:00:00'), self::fromLocal($next . ' 00:00:00'));
    }

    /* ==================== 对外接口（UTC 契约） ==================== */

    /** 存储串 → 对外 UTC 输出（ISO 8601 带 Z；两模式输出一致） */
    public static function isoUtc($v) {
        $t = self::ts($v);
        return $t === null ? '' : gmdate('Y-m-d\TH:i:s\Z', $t);
    }

    /**
     * 外部 UTC 入站（ISO 8601 'Z'/±偏移，或纯串按 UTC 解释）→ 存储值。
     * 契约：所有外部接口传输的时间均为标准 UTC。
     */
    public static function fromUtc($v) {
        $s = trim((string)$v);
        if ($s === '') return '';
        if (!preg_match('/[Zz]$|[+-]\d{2}:?\d{2}$/', $s)) $s .= ' UTC';
        $t = strtotime($s);
        if ($t === false) return '';
        return self::storageUtc() ? gmdate('Y-m-d H:i:s', $t) : date('Y-m-d H:i:s', $t);
    }

    /* ==================== 内部 API 输出漏斗 ==================== */

    /**
     * 递归本地化：数组中所有 *_at / *_time 键值经 local() 换算为站点时区显示串。
     * 供 Response 统一调用——「接入的 API 自动时区转换后给前端显示」。
     * 非时间键、空值、不可解析值一律原样保留。
     */
    public static function localize($data) {
        if (!is_array($data)) return $data;
        $out = array();
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = self::localize($v);
            } elseif (is_string($k) && preg_match('/_at$|_time$/', $k)) {
                $out[$k] = self::local($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
