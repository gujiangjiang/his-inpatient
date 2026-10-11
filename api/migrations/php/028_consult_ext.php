<?php
// api/migrations/php/028_consult_ext.php - 会诊单扩展字段 (类型/计划时间/病情摘要/初步诊断/参加科室)
return [
    'version' => '028',
    'description' => '会诊表增加会诊类型/计划时间/病情摘要/初步诊断/参加会诊科室',
    'up' => function($m) {
        if (!$m->hasTable('consultations')) return;
        foreach ([
            'consult_type' => ['type' => 'VARCHAR', 'length' => '50', 'default' => '普通会诊'],
            'planned_at' => ['type' => 'TEXT'],
            'summary' => ['type' => 'TEXT'],
            'diagnosis' => ['type' => 'TEXT'],
            'join_depts' => ['type' => 'VARCHAR', 'length' => '200'],
        ] as $col => $def) {
            if (!$m->hasColumn('consultations', $col)) {
                $m->addColumn('consultations', $col, $def);
            }
        }
    }
];
