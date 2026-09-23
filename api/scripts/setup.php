<?php
// api/scripts/setup.php - 主库初始化
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();

// 读取迁移文件
$sql = file_get_contents(__DIR__ . '/../migrations/sqlite.sql');
$pdo->exec($sql);

// 标记未初始化（setup_completed=0）
$configExists = $pdo->query("SELECT COUNT(*) as c FROM system_config WHERE config_key = 'setup_completed'")->fetch()['c'];
if (!$configExists) {
    $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value, config_group, description) VALUES (?, ?, ?, ?)");
    $stmt->execute(['setup_completed', '0', 'system', '系统是否已初始化']);
}

// 基础配置项
$defaults = [
    ['hospital_name', '', 'system', '医院名称'],
    ['hospital_code', '', 'system', '组织机构代码'],
];
foreach ($defaults as $d) {
    $check = $pdo->query("SELECT COUNT(*) as c FROM system_config WHERE config_key = '{$d[0]}'")->fetch()['c'];
    if (!$check) {
        $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value, config_group, description) VALUES (?, ?, ?, ?)");
        $stmt->execute($d);
    }
}

echo "初始化完成。数据库已创建，系统未初始化（需通过初始化向导创建管理员）。\n";
