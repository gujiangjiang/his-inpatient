<?php
// api/migrations/php/030_case_home.php - 病案首页结构化数据
return [
    'version' => '030',
    'description' => 'case_front_page 增加 data_json 结构化病案首页数据',
    'up' => function($m) {
        if (!$m->hasTable('case_front_page')) return;
        if (!$m->hasColumn('case_front_page', 'data_json')) {
            $m->addColumn('case_front_page', 'data_json', ['type' => 'TEXT']);
        }
    }
];
