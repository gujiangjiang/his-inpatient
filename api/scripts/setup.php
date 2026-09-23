<?php
// api/scripts/setup.php - 主库初始化 (使用新迁移系统)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/migration.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
$migration = new Migration();
$migration->enableForeignKeys();

// 按顺序执行迁移
$migrationDir = __DIR__ . '/../migrations/php';
$migrationFiles = glob($migrationDir . '/*.php');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $migrationFn = require $file;
    if (!is_array($migrationFn) || !isset($migrationFn['version']) || !isset($migrationFn['up'])) {
        continue;
    }
    $version = $migrationFn['version'];
    if ($migration->isMigrated($version)) {
        echo "迁移 {$version} 已跳过: " . $migrationFn['description'] . "\n";
        continue;
    }
    $migrationFn['up']($migration);
    $migration->recordMigration($version);
    echo "迁移 {$version} 完成: " . $migrationFn['description'] . "\n";
}

echo "数据库初始化完成。\n";
