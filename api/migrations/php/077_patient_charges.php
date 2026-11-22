<?php
// api/migrations/php/077_patient_charges.php - 患者费用流水 (费用核对/补录)
// 费用补录: 护士录入低值耗材等费用; 费用核对: 转科/出院前展示全部费用
return [
    'version' => '077',
    'description' => '患者费用流水表 (费用补录/核对)',
    'up' => function($m) {
        if ($m->hasTable('patient_charges')) return;
        $m->createTable('patient_charges', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
            'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
            'charge_type' => ['type' => 'VARCHAR', 'length' => '20', 'notnull' => true], // care 护理 / medical 医疗 / drug 药品 / material 耗材 / other 其他
            'item_name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],  // 项目名称
            'qty' => ['type' => 'REAL', 'default' => 1],                                 // 数量
            'price' => ['type' => 'REAL', 'default' => 0],                               // 单价
            'amount' => ['type' => 'REAL', 'default' => 0],                              // 金额 (qty*price)
            'source' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'manual'],  // manual 手工补录 / order 医嘱 / prepay 预交
            'ref_id' => ['type' => 'INTEGER'],                                           // 关联医嘱/单据
            'remark' => ['type' => 'TEXT'],
            'created_by' => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true, 'foreign_keys' => [
            ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
            ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
        ]]);
        $m->createIndex('patient_charges', 'idx_pc_patient_type', ['patient_id', 'charge_type']);
    }
];
