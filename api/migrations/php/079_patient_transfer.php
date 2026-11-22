<?php
// api/migrations/php/079_patient_transfer.php - 患者转科标记
// 转科 = 本科室离开操作: 患者标记 transferred + 目标科室名
return [
    'version' => '079',
    'description' => 'patients 增加 transferred_to 转科目标科室列',
    'up' => function($m) {
        if (!$m->hasTable('patients')) return;
        if (!$m->hasColumn('patients', 'transferred_to')) {
            $m->addColumn('patients', 'transferred_to', ['type' => 'VARCHAR', 'length' => '100']);
        }
    }
];
