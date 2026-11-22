<?php
// api/migrations/php/081_print_snapshots.php - 医疗打印法律快照（仅首次写入，不可更新）
return [
    'version' => '081',
    'description' => '创建不可变打印快照表（数据JSON + SHA-256完整性哈希）',
    'up' => function($m) {
        if ($m->hasTable('print_snapshots')) return;
        $m->createTable('print_snapshots', [
            'snapshot_key' => ['type' => 'VARCHAR', 'length' => '160', 'primary' => true],
            'document_type' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
            'business_id' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
            'patient_id' => ['type' => 'INTEGER'],
            'admission_no' => ['type' => 'VARCHAR', 'length' => '50'],
            'snapshot_json' => ['type' => 'TEXT', 'notnull' => true],
            'content_hash' => ['type' => 'VARCHAR', 'length' => '64', 'notnull' => true],
            'created_by' => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'TEXT', 'notnull' => true],
        ]);
        $m->createIndex('print_snapshots', 'idx_print_snapshot_patient', ['patient_id', 'document_type']);
    }
];
