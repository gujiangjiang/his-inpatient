<?php
// api/scripts/migrate.php - 增量迁移 (执行未完成的迁移)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/migration.php';
require_once __DIR__ . '/../core/helpers/time_helper.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

DB::getPDO();   // 建立连接（失败时给出统一错误）
$applied = Migration::runAll();
if ($applied === 0) {
    echo "无待执行的迁移。\n";
} else {
    echo "迁移完成，共 {$applied} 个。\n";
}
