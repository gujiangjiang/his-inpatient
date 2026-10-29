<?php
// api/scripts/setup.php - 主库初始化 (执行全部未完成迁移)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/migration.php';
require_once __DIR__ . '/../core/helpers/time_helper.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

DB::getPDO();   // 建立连接（失败时给出统一错误）
$applied = Migration::runAll();
echo "数据库初始化完成（本次执行 {$applied} 个迁移）。\n";
