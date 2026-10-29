<?php
// api/modules/setup/migrate.php - 向导第 3 步：执行数据库初始化迁移（幂等）
require_once __DIR__ . '/_common.php';
setup_require_open();
setup_require_stage(array('migrate', 'site'));   // 允许重复执行（增量补齐）

$applied = Migration::runAll();

// 校验关键表就绪
$okTable = false;
try {
    $okTable = DB::table('system_config');
} catch (Exception $e) { /* 连接异常 */ }
if (!$okTable) Response::error('数据库初始化不完整（核心表缺失），请检查数据库权限或日志', 500);

ConfigDB::set('setup.stage', 'site');
Response::success(array('applied' => $applied, 'stage' => 'site'), $applied > 0 ? "已完成 {$applied} 个迁移" : '数据库已就绪');
