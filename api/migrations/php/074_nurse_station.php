<?php
// api/migrations/php/074_nurse_station.php - 护士站重构: 体温单/护理记录单/常规护理文书
// 床位一览表为护士站主入口; 这些表支撑护理文书的结构化录入与展示
return [
    'version' => '074',
    'description' => '护士站护理文书: 体温单 / 护理记录单 / 常规护理文书',
    'up' => function($m) {
        // 体温单 (结构化: 每天多时点, 用于绘制体温单曲线)
        if (!$m->hasTable('temperature_charts')) {
            $m->createTable('temperature_charts', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'record_date' => ['type' => 'TEXT', 'notnull' => true],   // 业务日期 YYYY-MM-DD (本地墙钟)
                'time_slot' => ['type' => 'VARCHAR', 'length' => '20'],  // 时点: 06:00 / 10:00 / 14:00 / 18:00 / 22:00 等
                'temperature' => ['type' => 'REAL'],
                'pulse' => ['type' => 'REAL'],
                'respiratory_rate' => ['type' => 'REAL'],
                'bp_systolic' => ['type' => 'REAL'],
                'bp_diastolic' => ['type' => 'REAL'],
                'oxygen_saturation' => ['type' => 'REAL'],
                'intake_ml' => ['type' => 'REAL'],      // 入量 ml
                'output_ml' => ['type' => 'REAL'],      // 出量 ml
                'notes' => ['type' => 'TEXT'],
                'recorded_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'recorded_by', 'references' => 'users', 'on' => 'id']
            ]]);
            $m->createIndex('temperature_charts', 'idx_tc_patient_date', ['patient_id', 'record_date']);
        }

        // 护理记录单 (按时间序的护理观察/措施流水)
        if (!$m->hasTable('nursing_notes')) {
            $m->createTable('nursing_notes', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'record_time' => ['type' => 'TEXT', 'notnull' => true],   // 记录时间 (本地墙钟)
                'category' => ['type' => 'VARCHAR', 'length' => '30', 'default' => 'observation'], // observation 病情观察 / measure 测量 / care 护理措施 / medication 给药 / transfer 转科 / other
                'content' => ['type' => 'TEXT', 'notnull' => true],
                'temperature' => ['type' => 'REAL'],
                'pulse' => ['type' => 'REAL'],
                'respiratory_rate' => ['type' => 'REAL'],
                'bp_systolic' => ['type' => 'REAL'],
                'bp_diastolic' => ['type' => 'REAL'],
                'intake_ml' => ['type' => 'REAL'],
                'output_ml' => ['type' => 'REAL'],
                'recorded_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'recorded_by', 'references' => 'users', 'on' => 'id']
            ]]);
            $m->createIndex('nursing_notes', 'idx_nn_patient_time', ['patient_id', 'record_time']);
        }

        // 常规护理文书 (入院评估/护理计划/健康宣教/转科记录等)
        if (!$m->hasTable('nursing_docs')) {
            $m->createTable('nursing_docs', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'doc_type' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true], // admission_assessment 入院评估 / nursing_plan 护理计划 / health_edu 健康宣教 / transfer_record 转科记录 / other
                'title' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
                'content' => ['type' => 'TEXT', 'notnull' => true],
                'recorded_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
                'updated_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'recorded_by', 'references' => 'users', 'on' => 'id']
            ]]);
            $m->createIndex('nursing_docs', 'idx_nd_patient_type', ['patient_id', 'doc_type']);
        }
    }
];