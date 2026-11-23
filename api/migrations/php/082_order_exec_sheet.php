<?php
// api/migrations/php/082_order_exec_sheet.php - 药品执行单打印留痕
return [
    'version' => '082',
    'description' => '执行单打印记录表 (order_exec_sheet_prints)',
    'up' => function($m) {
        if ($m->hasTable('order_exec_sheet_prints')) return;
        $m->createTable('order_exec_sheet_prints', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INTEGER', 'notnull' => true],   // 根医嘱ID (组合医嘱整组一次打印)
            'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
            'printed_by' => ['type' => 'INTEGER'],
            'printed_at' => ['type' => 'TEXT', 'notnull' => true],
        ]);
        $m->createIndex('order_exec_sheet_prints', 'idx_exec_sheet_patient', ['patient_id', 'order_id']);
    }
];