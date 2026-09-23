<?php
// api/migrations/php/004_patient.php - 患者
return [
    'version' => '004',
    'description' => '创建患者表',
    'up' => function($m) {
        if ($m->hasTable('patients')) return;
        $m->createTable('patients', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'patient_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
            'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
            'gender' => ['type' => 'VARCHAR', 'length' => '10'],
            'birth_date' => ['type' => 'TEXT'],
            'id_card' => ['type' => 'VARCHAR', 'length' => '18'],
            'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
            'department_id' => ['type' => 'INTEGER'],
            'ward_id' => ['type' => 'INTEGER'],
            'bed_no' => ['type' => 'VARCHAR', 'length' => '20'],
            'admission_date' => ['type' => 'TEXT'],
            'admission_diagnosis' => ['type' => 'TEXT'],
            'admission_icd_code' => ['type' => 'VARCHAR', 'length' => '20'],
            'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'active'],
            'created_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true, 'foreign_keys' => [
            ['column' => 'department_id', 'references' => 'departments', 'on' => 'id'],
            ['column' => 'ward_id', 'references' => 'wards', 'on' => 'id']
        ]]);
        if ($m->getDriver() !== 'postgresql') {
            $m->execute("CREATE UNIQUE INDEX idx_patients_no ON patients(patient_no)");
            $m->execute("CREATE UNIQUE INDEX idx_patients_admission ON patients(admission_no)");
        }
    }
];
