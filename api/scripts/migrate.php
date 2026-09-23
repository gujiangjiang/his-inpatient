<?php
// api/scripts/migrate.php - 旧库增量 ALTER
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';

$pdo = DB::getPDO();

// 检查 orders 表是否已有 parent_id
$cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');

if (!in_array('parent_id', $colNames)) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN parent_id INTEGER");
    $pdo->exec("ALTER TABLE orders ADD COLUMN is_group_main INTEGER DEFAULT 0");
    echo "orders 表已更新 (parent_id, is_group_main)\n";
}

// 添加 is_auto_generated 字段 (M7 升级)
if (!in_array('is_auto_generated', $colNames)) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN is_auto_generated INTEGER DEFAULT 0");
    echo "orders 表已更新 (is_auto_generated)\n";
}

if (!in_array('content_delta', $colNames)) {
    // 检查 emr_records 表
    $emrCols = $pdo->query("PRAGMA table_info(emr_records)")->fetchAll(PDO::FETCH_ASSOC);
    $emrColNames = array_column($emrCols, 'name');
    if (!in_array('content_delta', $emrColNames)) {
        $pdo->exec("ALTER TABLE emr_records ADD COLUMN content_delta TEXT");
    }
    if (!in_array('content_html', $emrColNames)) {
        $pdo->exec("ALTER TABLE emr_records ADD COLUMN content_html TEXT");
    }
    echo "emr_records 表已更新 (content_delta, content_html)\n";
}

// 检查病案首页表
$tableList = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='case_front_page'")->fetch();
if (!$tableList) {
    $sql = file_get_contents(__DIR__ . '/../migrations/sqlite.sql');
    // 只执行创建病案首页相关的表
    preg_match_all('/CREATE TABLE IF NOT EXISTS (case_front_page|surgeries|discharge_records|dispensing_records|nursing_records|vital_signs|lab_reports|lab_report_items|exam_reports|api_configs).*?\);/s', $sql, $matches);
    foreach ($matches[0] as $create) {
        $pdo->exec($create);
    }
    echo "必要表已创建\n";
}

echo "迁移完成。\n";
