<?php
// api/migrations/php/032_patient_funds.php - 患者预交费用与可透支额度
return [
    'version' => '032',
    'description' => 'patients 增加 prepay_fee 预交费用与 overdraft_limit 可透支额度 (默认 1000)',
    'up' => function($m) {
        if (!$m->hasTable('patients')) return;
        if (!$m->hasColumn('patients', 'prepay_fee')) {
            $m->addColumn('patients', 'prepay_fee', ['type' => 'REAL', 'default' => 0]);
        }
        if (!$m->hasColumn('patients', 'overdraft_limit')) {
            $m->addColumn('patients', 'overdraft_limit', ['type' => 'REAL', 'default' => 1000]);
        }
    }
];
