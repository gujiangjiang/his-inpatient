<?php
// api/scripts/migrate.php - 增量迁移 (执行未完成的迁移)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/migration.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
$migration = new Migration();
$migration->enableForeignKeys();

$migrationDir = __DIR__ . '/../migrations/php';
$migrationFiles = glob($migrationDir . '/*.php');
sort($migrationFiles);

$applied = 0;
foreach ($migrationFiles as $file) {
    $migrationFn = require $file;
    if (!is_array($migrationFn) || !isset($migrationFn['version']) || !isset($migrationFn['up'])) {
        continue;
    }
    $version = $migrationFn['version'];
    if ($migration->isMigrated($version)) {
        continue;
    }
    $migrationFn['up']($migration);
    $migration->recordMigration($version);
    echo "迁移 {$version} 完成: " . $migrationFn['description'] . "\n";
    $applied++;
}

if ($applied === 0) {
    echo "无待执行的迁移。\n";
} else {
    echo "迁移完成，共 {$applied} 个。\n";
}
