<?php
// api/migrations/php/012_patient_ext.php - 患者卡片墙扩展字段
return [
    'version' => '012',
    'description' => '患者表扩展: 护理等级/费别/过敏/危重/VTE/费用',
    'up' => function($m) {
        if (!$m->hasTable('patients')) return;

        $columns = [
            'nursing_level' => ['type' => 'VARCHAR', 'length' => '20', 'default' => '3'],   // special|1|2|3 特/一/二/三级护理
            'fee_type' => ['type' => 'VARCHAR', 'length' => '50'],                          // 职工医保|居民医保|自费|新农合
            'allergy_history' => ['type' => 'TEXT'],                                        // 过敏史 (非空即卡片标红)
            'is_critical' => ['type' => 'INTEGER', 'default' => 0],                         // 危重标记
            'vte_caprini' => ['type' => 'VARCHAR', 'length' => '20'],                       // VTE 静脉血栓风险: 低危|中危|高危
            'vte_bleeding' => ['type' => 'VARCHAR', 'length' => '20'],                      // 出血风险: 低危|中危|高危
            'total_fee' => ['type' => 'REAL', 'default' => 0],                              // 累计费用
        ];
        foreach ($columns as $name => $def) {
            if (!$m->hasColumn('patients', $name)) {
                $m->addColumn('patients', $name, $def);
            }
        }

        $m->createIndex('patients', 'idx_patients_nursing_level', ['nursing_level']);
    }
];
