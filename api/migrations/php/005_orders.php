<?php
// api/migrations/php/005_orders.php - 医嘱
return [
    'version' => '005',
    'description' => '创建医嘱表 (含成组子医嘱)',
    'up' => function($m) {
        if ($m->hasTable('orders')) return;
        $m->createTable('orders', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
            'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
            'parent_id' => ['type' => 'INTEGER'],
            'is_group_main' => ['type' => 'INTEGER', 'default' => 0],
            'order_type' => ['type' => 'VARCHAR', 'length' => '50'],
            'content' => ['type' => 'TEXT'],
            'category' => ['type' => 'VARCHAR', 'length' => '50'],
            'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'draft'],
            'is_auto_generated' => ['type' => 'INTEGER', 'default' => 0],
            'dosage' => ['type' => 'VARCHAR', 'length' => '100'],
            'frequency' => ['type' => 'VARCHAR', 'length' => '50'],
            'duration' => ['type' => 'VARCHAR', 'length' => '100'],
            'created_by' => ['type' => 'INTEGER'],
            'verified_by' => ['type' => 'INTEGER'],
            'verified_at' => ['type' => 'TEXT'],
            'start_date' => ['type' => 'TEXT'],
            'end_date' => ['type' => 'TEXT'],
            'notes' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'TEXT'],
            'updated_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true, 'foreign_keys' => [
            ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
            ['column' => 'parent_id', 'references' => 'orders', 'on' => 'id'],
            ['column' => 'created_by', 'references' => 'users', 'on' => 'id'],
            ['column' => 'verified_by', 'references' => 'users', 'on' => 'id']
        ]]);
    }
];
