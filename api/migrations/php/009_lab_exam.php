<?php
// api/migrations/php/009_lab_exam.php - 检验检查
return [
    'version' => '009',
    'description' => '创建检验报告、检查报告表',
    'up' => function($m) {
        // 检验报告
        if (!$m->hasTable('lab_reports')) {
            $m->createTable('lab_reports', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'report_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'exam_name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'specimen_type' => ['type' => 'VARCHAR', 'length' => '50'],
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'pending'],
                'is_critical' => ['type' => 'INTEGER', 'default' => 0],
                'reported_by' => ['type' => 'INTEGER'],
                'reported_at' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'reported_by', 'references' => 'users', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
            if ($m->getDriver() !== 'postgresql') {
                $m->execute("CREATE UNIQUE INDEX idx_lab_reports_no ON lab_reports(report_no)");
            }
        }

        // 检验明细
        if (!$m->hasTable('lab_report_items')) {
            $m->createTable('lab_report_items', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'report_id' => ['type' => 'INTEGER', 'notnull' => true],
                'item_name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'result' => ['type' => 'VARCHAR', 'length' => '200'],
                'unit' => ['type' => 'VARCHAR', 'length' => '50'],
                'reference_range' => ['type' => 'VARCHAR', 'length' => '200'],
                'flag' => ['type' => 'VARCHAR', 'length' => '20'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'report_id', 'references' => 'lab_reports', 'on' => 'id']
            ]]);
        }

        // 检查报告
        if (!$m->hasTable('exam_reports')) {
            $m->createTable('exam_reports', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'report_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'exam_type' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'body_part' => ['type' => 'VARCHAR', 'length' => '200'],
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'pending'],
                'findings' => ['type' => 'TEXT'],
                'conclusion' => ['type' => 'TEXT'],
                'impression' => ['type' => 'TEXT'],
                'reported_by' => ['type' => 'INTEGER'],
                'reported_at' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'reported_by', 'references' => 'users', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
            if ($m->getDriver() !== 'postgresql') {
                $m->execute("CREATE UNIQUE INDEX idx_exam_reports_no ON exam_reports(report_no)");
            }
        }
    }
];
