<?php
// api/migrations/php/043_emr_template_dept.php - 模板科室归属
// 模板按科室管理: 本科室模板 / 全院模板(分组展示); NULL = 全院通用(内置模板)
return [
    'version' => '043',
    'description' => 'emr_templates 增加 department_id (模板科室归属)',
    'up' => function($m) {
        if ($m->hasTable('emr_templates') && !$m->hasColumn('emr_templates', 'department_id')) {
            $m->addColumn('emr_templates', 'department_id', ['type' => 'INTEGER']);
        }
    }
];
