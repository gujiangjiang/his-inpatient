<?php
// api/scripts/export_emr_templates.php - 导出内置病历模板为 JSON 文件 (docs/emr_templates/)
// 与运行时同一份定义 (api/core/emr_template_defs.php), 保证导出与系统内置一致
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/time_helper.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

require_once __DIR__ . '/../core/branding.php';
$names = branding_names();

$defsFn = require __DIR__ . '/../core/emr_template_defs.php';
$defs = $defsFn($names['hospital_name'], $names['hospital_name2']);

$dir = dirname(dirname(__DIR__)) . '/docs/emr_templates';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$count = 0;
foreach ($defs as $i => $d) {
    list($name, $recordType, $catCode, $schema) = $d;
    $payload = [
        'name' => $name,
        'record_type' => $recordType,
        'category' => $catCode,
        'schema' => $schema,
    ];
    $file = $dir . '/' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '_' . $name . '.json';
    file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    $count++;
    echo "导出: " . basename($file) . "\n";
}
echo "完成, 共 {$count} 个模板 -> docs/emr_templates/\n";
