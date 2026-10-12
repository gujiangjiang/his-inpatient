<?php
// api/migrations/php/033_opd_order_push.php - 住院检验检查单推送门诊队列
// 说明: 住院开单即计费（HIS 侧收费口径），检验/检查申请提交后自动推送门诊
// 一体化系统登记出报告；推送失败入队重试，由 api/scripts/push_opd_orders.php
// （定时任务）或接口管理页「重推失败单据」补偿，同一单据幂等（biz_type+biz_id）。
return [
    'version' => '033',
    'description' => '创建住院检验检查单推送门诊队列 integration_order_pushes',
    'up' => function($m) {
        if ($m->hasTable('integration_order_pushes')) return;
        $m->createTable('integration_order_pushes', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'biz_type' => ['type' => 'VARCHAR', 'length' => '20', 'notnull' => true],
            'biz_id' => ['type' => 'INTEGER', 'notnull' => true],
            'report_no' => ['type' => 'VARCHAR', 'length' => '50'],
            'payload' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'pending'],
            'retry_count' => ['type' => 'INTEGER', 'default' => 0],
            'last_error' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'TEXT'],
            'updated_at' => ['type' => 'TEXT'],
        ]);
        $m->createIndex('integration_order_pushes', 'idx_io_pushes_status', ['status']);
        $m->createIndex('integration_order_pushes', 'idx_io_pushes_biz', ['biz_type', 'biz_id']);
    }
];
