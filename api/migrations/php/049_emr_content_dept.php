<?php
// api/migrations/php/049_emr_content_dept.php - 内容库科室归属
// 与模板一致: 本科室内容 / 全院内容 (NULL = 全院通用); 名称由 departments 实时 JOIN (更名同步)
return [
    'version' => '049',
    'description' => 'emr_contents 增加 department_id (内容科室归属)',
    'up' => function($m) {
        if ($m->hasTable('emr_contents') && !$m->hasColumn('emr_contents', 'department_id')) {
            $m->addColumn('emr_contents', 'department_id', ['type' => 'INTEGER']);
        }
    }
];
