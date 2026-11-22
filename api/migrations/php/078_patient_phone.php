<?php
// api/migrations/php/078_patient_phone.php - 患者联系电话 (患者信息/腕带预留)
return [
    'version' => '078',
    'description' => 'patients 增加 phone 联系电话列',
    'up' => function($m) {
        if (!$m->hasTable('patients')) return;
        if (!$m->hasColumn('patients', 'phone')) {
            $m->addColumn('patients', 'phone', ['type' => 'VARCHAR', 'length' => '30']);
        }
    }
];
