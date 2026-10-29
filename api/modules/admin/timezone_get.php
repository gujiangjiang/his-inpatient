<?php
// api/modules/admin/timezone_get.php - 全站时区读取（config.db）
Auth::requireRole(['admin']);
Response::success(array(
    'timezone' => ConfigDB::timezone('Asia/Shanghai'),
    'timezones' => DateTimeZone::listIdentifiers(),
));
