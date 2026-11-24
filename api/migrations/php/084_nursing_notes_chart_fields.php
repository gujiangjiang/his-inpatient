<?php
// api/migrations/php/084_nursing_notes_chart_fields.php - 护理记录单扩展: 体温单 (三测单) 所需字段
// 疼痛评分/体温测量途径/降温后复测/心率/起搏器/呼吸机/大便次数/小便量/皮试/过敏药物/特殊药物/其他备注
return [
    'version' => '084',
    'description' => '护理记录单扩展体温单所需字段 (疼痛评分/体温途径/降温复测/心率/大便/小便/皮试等)',
    'up' => function ($m) {
        if (!$m->hasTable('nursing_notes')) return;
        $cols = [
            'pain_score' => ['type' => 'INTEGER'],                 /* 疼痛评分 0-10 (独立区蓝三角曲线) */
            'temp_type' => ['type' => 'VARCHAR', 'length' => 20],  /* 体温测量途径 oral/axillary/rectal/ear */
            'cooling_temp' => ['type' => 'REAL'],                  /* 降温后半小时复测体温 (同格红圈+红虚线) */
            'heart_rate' => ['type' => 'INTEGER'],                 /* 心率 (与脉搏并存且>脉搏 → 短绌斜线) */
            'pacemaker' => ['type' => 'INTEGER'],                  /* 起搏器心率 1/0 (带 H 红圈) */
            'ventilator' => ['type' => 'INTEGER'],                 /* 使用呼吸机 1/0 (呼吸20次线上 ®) */
            'stool' => ['type' => 'VARCHAR', 'length' => 50],      /* 大便次数 (支持 0/E·3/2E·※·☆) */
            'urine_ml' => ['type' => 'REAL'],                      /* 小便量 ml (按日累计) */
            'skin_test' => ['type' => 'VARCHAR', 'length' => 20],  /* 皮试结果 (阳性红字/阴性黑字) */
            'allergy_drugs' => ['type' => 'VARCHAR', 'length' => 200],  /* 过敏药物 */
            'special_drugs' => ['type' => 'VARCHAR', 'length' => 200],  /* 特殊药物 */
            'other_remark' => ['type' => 'VARCHAR', 'length' => 200],   /* 其他备注 */
        ];
        foreach ($cols as $name => $def) {
            if (!$m->hasColumn('nursing_notes', $name)) $m->addColumn('nursing_notes', $name, $def);
        }
    }
];
