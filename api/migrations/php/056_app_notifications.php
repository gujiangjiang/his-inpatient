<?php
// 056_app_notifications.php - 全局消息推送 (app_notifications)
// 业务事件 → 定向通知 (角色/病区/个人): 模板内容审核(管理员)、审核结果(申请人)、
// 病历维护申请与结果、新待核医嘱(病区护士) 等; 处理完成后经 notify_resolve 自动消失。
// 推送走 SSE 长连接 (api/modules/notify/stream.php), 前端零轮询。
return [
    'version' => '056',
    'description' => '全局消息通知表 (app_notifications)',
    'up' => function ($m) {
        if (!$m->hasTable('app_notifications')) {
            $m->createTable('app_notifications', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'user_id' => ['type' => 'INTEGER', 'notnull' => true],
                'type' => ['type' => 'VARCHAR', 'length' => 30, 'notnull' => true],   /* template/content/maintenance/order/system */
                'title' => ['type' => 'VARCHAR', 'length' => 200, 'notnull' => true],
                'body' => ['type' => 'TEXT'],
                'link' => ['type' => 'VARCHAR', 'length' => 200],                    /* hash 路由: #admin/review 等 */
                'ref_type' => ['type' => 'VARCHAR', 'length' => 30],                 /* 关联业务类型 (template/maintenance/orders_pending...) */
                'ref_id' => ['type' => 'INTEGER'],                                   /* 关联业务 id */
                'meta' => ['type' => 'TEXT'],                                        /* 附加 JSON (如待处理医嘱 id 列表) */
                'is_read' => ['type' => 'INTEGER', 'default' => 0],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
        }
        $m->createIndex('app_notifications', 'idx_notify_user', ['user_id', 'id']);
        $m->createIndex('app_notifications', 'idx_notify_ref', ['ref_type', 'ref_id']);
    }
];
