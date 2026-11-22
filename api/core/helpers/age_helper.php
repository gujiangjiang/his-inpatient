<?php
// api/core/helpers/age_helper.php - 全项目统一临床年龄口径（与病案首页一致）
class AgeHelper {
    public static function parts($birthDate, $at = null) {
        $empty = ['y' => '', 'm' => '', 'd' => '', 'display' => ''];
        $birth = self::parse($birthDate);
        $date = self::parse($at ?: TimeHelper::nowLocal());
        if (!$birth || !$date) return $empty;
        $days = (int)$birth->diff($date)->format('%r%a');
        if ($days < 0) return $empty;
        if ($days >= 365) {
            $years = max(1, (int)floor($days / 365.25));
            return ['y' => $years, 'm' => '', 'd' => '', 'display' => $years . '岁'];
        }
        if ($days > 28) {
            $months = max(1, (int)floor($days / 30.44));
            return ['y' => '', 'm' => $months, 'd' => '', 'display' => $months . '月'];
        }
        return ['y' => '', 'm' => '', 'd' => $days, 'display' => $days . '天'];
    }

    public static function display($birthDate, $at = null) {
        $parts = self::parts($birthDate, $at);
        return $parts['display'];
    }

    private static function parse($value) {
        $text = trim((string)$value);
        if ($text === '') return null;
        try {
            return new DateTimeImmutable($text);
        } catch (Exception $e) {
            return null;
        }
    }
}
