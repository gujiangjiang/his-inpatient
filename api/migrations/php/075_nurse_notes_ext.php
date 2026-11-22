<?php
// api/migrations/php/075_nurse_notes_ext.php - 护理记录单扩展字段 + 首次护理记录
// 护理记录单成为生命体征/护理观察唯一数据源 (体温单从此表派生)
return [
    'version' => '075',
    'description' => '护理记录单扩展字段 (血氧/管路/引流/意识/血糖/瞳孔/GCS) + 首次护理记录标志',
    'up' => function($m) {
        if (!$m->hasTable('nursing_notes')) return;

        // 生命体征与护理观察扩展列 (均选填)
        $columns = [
            'oxygen_saturation' => ['type' => 'REAL'],          // 血氧 %
            'has_pipeline' => ['type' => 'VARCHAR', 'length' => '10'],  // 管路: 无/胃管/尿管/深静脉/引流管/其他
            'pipeline_desc' => ['type' => 'VARCHAR', 'length' => '100'], // 管路描述
            'drainage_ml' => ['type' => 'REAL'],                 // 引流量 ml
            'consciousness' => ['type' => 'VARCHAR', 'length' => '20'], // 意识情况: 清醒/嗜睡/昏睡/昏迷/模糊
            'blood_glucose' => ['type' => 'REAL'],               // 血糖 mmol/L
            'pupil' => ['type' => 'VARCHAR', 'length' => '100'], // 瞳孔情况: 等大等圆/对光反射 等
            'gcs_eye' => ['type' => 'INTEGER'],                  // GCS 睁眼反应 1-4
            'gcs_verbal' => ['type' => 'INTEGER'],               // GCS 语言反应 1-5
            'gcs_motor' => ['type' => 'INTEGER'],                // GCS 运动反应 1-6
            'note' => ['type' => 'TEXT'],                        // 护理记录 (文本书写, 原 content 保留兼容)
        ];
        foreach ($columns as $name => $def) {
            if (!$m->hasColumn('nursing_notes', $name)) {
                $m->addColumn('nursing_notes', $name, $def);
            }
        }

        // 首次护理记录标志 (category='first' + is_first=1)
        if (!$m->hasColumn('nursing_notes', 'is_first')) {
            $m->addColumn('nursing_notes', 'is_first', ['type' => 'INTEGER', 'default' => 0]);
        }
        $m->createIndex('nursing_notes', 'idx_nn_first', ['patient_id', 'is_first']);
    }
];