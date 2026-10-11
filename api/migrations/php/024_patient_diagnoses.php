<?php
// api/migrations/php/024_patient_diagnoses.php - 患者诊断(五分类)与常用诊断模板
return [
    'version' => '024',
    'description' => '创建患者诊断表 patient_diagnoses 与诊断模板表 diagnosis_templates',
    'up' => function($m) {
        if (!$m->hasTable('patient_diagnoses')) {
            $m->createTable('patient_diagnoses', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50'],
                'category' => ['type' => 'VARCHAR', 'length' => '50'],   // emergency|admission|discharge|pathology|external|nosocomial|complication|death_cause
                'icd_code' => ['type' => 'VARCHAR', 'length' => '50'],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'note' => ['type' => 'TEXT'],
                'sort_no' => ['type' => 'INTEGER', 'default' => 0],
                'created_by' => ['type' => 'INTEGER'],
                'created_by_name' => ['type' => 'VARCHAR', 'length' => '50'],
                'created_at' => ['type' => 'TEXT'],
            ]);
        }
        if (!$m->hasTable('diagnosis_templates')) {
            $m->createTable('diagnosis_templates', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'icd_code' => ['type' => 'VARCHAR', 'length' => '50'],
                'category' => ['type' => 'VARCHAR', 'length' => '50'],
                'created_by' => ['type' => 'INTEGER'],
                'dept_id' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ]);
        }
    }
];
