<?php
// api/migrations/php/080_lab_barcode_print.php - 检验条码打印留痕 + 报告更新时间字段修复
return [
    'version' => '080',
    'description' => '检验条码打印次数/打印人/打印时间，并补齐检验检查报告更新时间',
    'up' => function($m) {
        if ($m->hasTable('lab_reports')) {
            $columns = [
                'updated_at' => ['type' => 'TEXT'],
                'barcode_print_count' => ['type' => 'INTEGER', 'default' => 0],
                'barcode_printed_at' => ['type' => 'TEXT'],
                'barcode_printed_by' => ['type' => 'INTEGER'],
            ];
            foreach ($columns as $name => $definition) {
                if (!$m->hasColumn('lab_reports', $name)) $m->addColumn('lab_reports', $name, $definition);
            }
            $m->execute("UPDATE lab_reports SET updated_at = COALESCE(reported_at, created_at) WHERE updated_at IS NULL");
        }
        if ($m->hasTable('exam_reports') && !$m->hasColumn('exam_reports', 'updated_at')) {
            $m->addColumn('exam_reports', 'updated_at', ['type' => 'TEXT']);
            $m->execute("UPDATE exam_reports SET updated_at = COALESCE(reported_at, created_at) WHERE updated_at IS NULL");
        }
    }
];
