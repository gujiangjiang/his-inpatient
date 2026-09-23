<?php
// api/migrations/php/010_case_surgery.php - 病案首页 + 手术 + 出院记录
return [
    'version' => '010',
    'description' => '创建病案首页、手术记录、出院记录表',
    'up' => function($m) {
        // 病案首页
        if (!$m->hasTable('case_front_page')) {
            $m->createTable('case_front_page', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'content_delta' => ['type' => 'TEXT'],
                'content_html' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
                'updated_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        // 手术
        if (!$m->hasTable('surgeries')) {
            $m->createTable('surgeries', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'surgery_name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'surgeon' => ['type' => 'INTEGER'],
                'anesthesia_type' => ['type' => 'VARCHAR', 'length' => '50'],
                'surgery_date' => ['type' => 'TEXT'],
                'incision_healing' => ['type' => 'VARCHAR', 'length' => '200'],
                'complications' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'surgeon', 'references' => 'users', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        // 出院记录
        if (!$m->hasTable('discharge_records')) {
            $m->createTable('discharge_records', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'discharge_diagnosis' => ['type' => 'TEXT'],
                'discharge_icd_code' => ['type' => 'VARCHAR', 'length' => '20'],
                'discharge_advice' => ['type' => 'TEXT'],
                'discharged_at' => ['type' => 'TEXT'],
                'discharged_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'discharged_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        // 外部接口配置
        if (!$m->hasTable('api_configs')) {
            $m->createTable('api_configs', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'api_type' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'base_url' => ['type' => 'VARCHAR', 'length' => '500'],
                'auth_type' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'none'],
                'auth_config' => ['type' => 'TEXT'],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'last_sync' => ['type' => 'TEXT'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
        }
    }
];
