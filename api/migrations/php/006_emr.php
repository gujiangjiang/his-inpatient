<?php
// api/migrations/php/006_emr.php - 电子病历
return [
    'version' => '006',
    'description' => '创建 EMR 记录、模板表',
    'up' => function($m) {
        if (!$m->hasTable('emr_records')) {
            $m->createTable('emr_records', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'record_type' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'content_delta' => ['type' => 'TEXT'],
                'content_html' => ['type' => 'TEXT'],
                'diagnosis' => ['type' => 'TEXT'],
                'icd_code' => ['type' => 'VARCHAR', 'length' => '20'],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        if (!$m->hasTable('emr_templates')) {
            $m->createTable('emr_templates', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'record_type' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'content_delta' => ['type' => 'TEXT'],
                'content_html' => ['type' => 'TEXT'],
                'usage_count' => ['type' => 'INTEGER', 'default' => 0],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
        }
    }
];
