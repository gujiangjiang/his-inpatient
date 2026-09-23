<?php
// api/migrations/php/002_department.php - 科室/病区/床位
return [
    'version' => '002',
    'description' => '创建科室、病区、床位表',
    'up' => function($m) {
        // 科室
        if (!$m->hasTable('departments')) {
            $m->createTable('departments', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'description' => ['type' => 'TEXT'],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
            if ($m->getDriver() !== 'postgresql') {
                $m->execute("CREATE UNIQUE INDEX idx_departments_code ON departments(code)");
            }
        }

        // 病区
        if (!$m->hasTable('wards')) {
            $m->createTable('wards', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'floor' => ['type' => 'VARCHAR', 'length' => '20'],
                'bed_count' => ['type' => 'INTEGER', 'default' => 0],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
            if ($m->getDriver() !== 'postgresql') {
                $m->execute("CREATE UNIQUE INDEX idx_wards_code ON wards(code)");
            }
        }

        // 床位
        if (!$m->hasTable('beds')) {
            $m->createTable('beds', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'ward_id' => ['type' => 'INTEGER', 'notnull' => true],
                'bed_no' => ['type' => 'VARCHAR', 'length' => '20', 'notnull' => true],
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'free'],
                'patient_id' => ['type' => 'INTEGER'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'ward_id', 'references' => 'wards', 'on' => 'id']
            ]]);
        }
    }
];
