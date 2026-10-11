<?php
// api/migrations/php/023_consultations.php - 会诊申请与回复 + 医嘱"已会诊"标注
return [
    'version' => '023',
    'description' => '创建会诊表 consultations, orders 增加 consult_status',
    'up' => function($m) {
        if (!$m->hasTable('consultations')) {
            $m->createTable('consultations', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50'],
                'from_dept_id' => ['type' => 'INTEGER'],
                'from_dept_name' => ['type' => 'VARCHAR', 'length' => '100'],
                'to_dept_id' => ['type' => 'INTEGER'],
                'to_dept_name' => ['type' => 'VARCHAR', 'length' => '100'],
                'purpose' => ['type' => 'TEXT'],                      // 会诊目的
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'pending'], // pending|replied
                'reply_text' => ['type' => 'TEXT'],                   // 会诊意见
                'replied_by' => ['type' => 'INTEGER'],
                'replied_by_name' => ['type' => 'VARCHAR', 'length' => '50'],
                'replied_at' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INTEGER'],
                'created_by_name' => ['type' => 'VARCHAR', 'length' => '50'],
                'order_id' => ['type' => 'INTEGER'],                  // 关联的"请X科会诊"医嘱
                'created_at' => ['type' => 'TEXT'],
            ]);
        }
        if ($m->hasTable('orders') && !$m->hasColumn('orders', 'consult_status')) {
            $m->addColumn('orders', 'consult_status', ['type' => 'VARCHAR', 'length' => '20', 'default' => '']);
        }
    }
];
