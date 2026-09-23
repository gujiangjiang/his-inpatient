<?php
// api/migrations/php/007_nursing.php - 护理记录 + 生命体征
return [
    'version' => '007',
    'description' => '创建护理记录、生命体征表',
    'up' => function($m) {
        // 护理记录
        if (!$m->hasTable('nursing_records')) {
            $m->createTable('nursing_records', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'order_id' => ['type' => 'INTEGER', 'notnull' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'action' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'executed_by' => ['type' => 'INTEGER', 'notnull' => true],
                'executed_at' => ['type' => 'TEXT'],
                'notes' => ['type' => 'TEXT'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'order_id', 'references' => 'orders', 'on' => 'id'],
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'executed_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        // 生命体征
        if (!$m->hasTable('vital_signs')) {
            $m->createTable('vital_signs', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'recorded_by' => ['type' => 'INTEGER'],
                'recorded_at' => ['type' => 'TEXT'],
                'temperature' => ['type' => 'REAL'],
                'pulse' => ['type' => 'REAL'],
                'blood_pressure_systolic' => ['type' => 'REAL'],
                'blood_pressure_diastolic' => ['type' => 'REAL'],
                'respiratory_rate' => ['type' => 'REAL'],
                'oxygen_saturation' => ['type' => 'REAL'],
                'height' => ['type' => 'REAL'],
                'weight' => ['type' => 'REAL'],
                'notes' => ['type' => 'TEXT'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'recorded_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }
    }
];
