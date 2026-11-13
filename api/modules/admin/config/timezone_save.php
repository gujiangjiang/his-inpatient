<?php
// api/modules/admin/timezone_save.php - 全站时区保存（config.db；存储始终 UTC，仅显示口径）
Auth::requireRole(['admin']);
$tz = trim((string)Validator::get('timezone', ''));
if ($tz === '') Response::error('请选择时区');
try {
    new DateTimeZone($tz);
} catch (Exception $e) {
    Response::error('无效的时区：' . $tz);
}
if (!ConfigDB::set('timezone', $tz)) {
    Response::error('配置写入失败，请检查 data/ 目录权限', 500);
}
date_default_timezone_set($tz);   // 当前请求即时生效
Response::success(array('timezone' => $tz), '时区已保存');
