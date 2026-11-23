<?php
// api/scripts/schema/setup.php - 主库初始化 (执行全部未完成迁移)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/db/db.php';
require_once __DIR__ . '/../../core/schema/migration.php';
require_once __DIR__ . '/../../core/helpers/time/time_helper.php';
require_once __DIR__ . '/../../core/helpers/time/date_helper.php';

DB::getPDO();   // 建立连接（失败时给出统一错误）
$applied = Migration::runAll();
echo "数据库初始化完成（本次执行 {$applied} 个迁移）。\n";
