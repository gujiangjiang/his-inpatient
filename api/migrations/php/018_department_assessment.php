<?php
// api/migrations/php/018_department_assessment.php - 科室风险评估量表配置 (占位)
return [
    'version' => '018',
    'description' => 'departments 增加风险评估量表字段 risk_assessment',
    'up' => function($m) {
        if (!$m->hasTable('departments')) return;
        if (!$m->hasColumn('departments', 'risk_assessment')) {
            $m->addColumn('departments', 'risk_assessment', [
                'type' => 'VARCHAR', 'length' => '50', 'default' => 'caprini'
            ]);
        }
    }
];
