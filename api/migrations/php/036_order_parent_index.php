<?php
// api/migrations/php/036_order_parent_index.php - 医嘱父子关系索引（开医嘱列表子医嘱批量查询性能）
return [
    'version' => '036',
    'description' => 'orders 增加 parent_id 索引',
    'up' => function($m) {
        if (!$m->hasTable('orders')) return;
        $m->createIndex('orders', 'idx_orders_parent', ['parent_id']);
    }
];
