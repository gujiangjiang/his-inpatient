<?php
// api/migrations/php/073_emr_print_count.php - 文书打印次数统计
// emr_documents.print_count: 每次批量打印累加; 病历维护批准(退回草稿重新编辑)后清零
return [
    'version' => '073',
    'description' => '文书打印次数: emr_documents.print_count + 维护批准清零',
    'up' => function($m) {
        if ($m->hasTable('emr_documents') && !$m->hasColumn('emr_documents', 'print_count')) {
            $m->addColumn('emr_documents', 'print_count', ['type' => 'INTEGER', 'default' => 0]);
        }
    }
];