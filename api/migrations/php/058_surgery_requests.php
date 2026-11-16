<?php
// 058_surgery_requests.php - 手术申请功能 (surgeries 表扩展)
// 申请字段: 类别(择期/急诊/日间)/体位/术前评估地点/手术位置/一助二助/状态/关联医嘱/计划时间
return [
    'version' => '058',
    'description' => '手术申请: surgeries 扩展 (类别/体位/地点/位置/助手/状态/关联医嘱)',
    'up' => function ($m) {
        if (!$m->hasTable('surgeries')) return;
        $cols = [
            'category' => ['type' => 'VARCHAR', 'length' => 20],          /* elective/emergency/day */
            'patient_position' => ['type' => 'VARCHAR', 'length' => 50],   /* 体位 */
            'assess_place' => ['type' => 'VARCHAR', 'length' => 50],       /* 术前评估地点 */
            'side' => ['type' => 'VARCHAR', 'length' => 20],               /* 左侧/右侧/双侧 */
            'assist1_id' => ['type' => 'INTEGER'],
            'assist2_id' => ['type' => 'INTEGER'],
            'status' => ['type' => 'VARCHAR', 'length' => 20, 'default' => 'requested'], /* requested/cancelled */
            'order_id' => ['type' => 'INTEGER'],
            'planned_at' => ['type' => 'TEXT'],
            'requested_at' => ['type' => 'TEXT'],
            'requested_by' => ['type' => 'INTEGER'],
            'cancelled_at' => ['type' => 'TEXT'],
            'cancelled_by' => ['type' => 'INTEGER'],
            'cancel_reason' => ['type' => 'TEXT'],
            'notes' => ['type' => 'TEXT'],
        ];
        foreach ($cols as $name => $def) {
            if (!$m->hasColumn('surgeries', $name)) $m->addColumn('surgeries', $name, $def);
        }
        $m->createIndex('surgeries', 'idx_surgeries_status', ['status']);
        $m->createIndex('surgeries', 'idx_surgeries_order', ['order_id']);
    }
];
