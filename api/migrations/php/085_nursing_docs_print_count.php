<?php
// api/migrations/php/085_nursing_docs_print_count.php - 护理文书打印统计
// print_count 累计打印次数 (批量打印/单份打印共用), printed_at 最近一次打印时间
return [
    'version' => '085',
    'description' => '护理文书增加打印次数字段 (print_count/printed_at), 供批量打印弹窗显示与默认勾选未打印',
    'up' => function ($m) {
        if (!$m->hasTable('nursing_docs')) return;
        if (!$m->hasColumn('nursing_docs', 'print_count')) {
            $m->addColumn('nursing_docs', 'print_count', ['type' => 'INTEGER', 'default' => 0]);
        }
        if (!$m->hasColumn('nursing_docs', 'printed_at')) {
            $m->addColumn('nursing_docs', 'printed_at', ['type' => 'TEXT', 'nullable' => true]);
        }
    }
];
