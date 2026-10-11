<?php
// api/migrations/php/027_patient_diag_match.php - 诊断符合情况
return [
    'version' => '027',
    'description' => '创建患者诊断符合情况表 patient_diag_match',
    'up' => function($m) {
        if ($m->hasTable('patient_diag_match')) return;
        $m->createTable('patient_diag_match', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
            'mkey' => ['type' => 'VARCHAR', 'length' => '50'],   // 符合情况项: out_in/adm_out/...
            'mvalue' => ['type' => 'VARCHAR', 'length' => '10'], // ''|0|1|2|3
            'updated_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true]);
    }
];
