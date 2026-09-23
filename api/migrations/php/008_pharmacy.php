<?php
// api/migrations/php/008_pharmacy.php - 药房: 药品库存 + 发药记录
return [
    'version' => '008',
    'description' => '创建药品库存、发药记录表',
    'up' => function($m) {
        if (!$m->hasTable('medications')) {
            $m->createTable('medications', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'specification' => ['type' => 'VARCHAR', 'length' => '200'],
                'unit' => ['type' => 'VARCHAR', 'length' => '20'],
                'price' => ['type' => 'REAL'],
                'stock_quantity' => ['type' => 'INTEGER', 'default' => 0],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
            if ($m->getDriver() !== 'postgresql') {
                $m->execute("CREATE UNIQUE INDEX idx_medications_code ON medications(code)");
            }
        }

        if (!$m->hasTable('dispensing_records')) {
            $m->createTable('dispensing_records', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'order_id' => ['type' => 'INTEGER'],
                'medication_id' => ['type' => 'INTEGER', 'notnull' => true],
                'quantity' => ['type' => 'INTEGER', 'notnull' => true],
                'dispensed_by' => ['type' => 'INTEGER', 'notnull' => true],
                'dispensed_at' => ['type' => 'TEXT'],
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'dispensed'],
                'notes' => ['type' => 'TEXT'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'order_id', 'references' => 'orders', 'on' => 'id'],
                ['column' => 'medication_id', 'references' => 'medications', 'on' => 'id'],
                ['column' => 'dispensed_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }
    }
];
