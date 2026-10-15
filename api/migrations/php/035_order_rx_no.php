<?php
// api/migrations/php/035_order_rx_no.php - 处方号 (毁方/处方列表按方归集)
return [
    'version' => '035',
    'description' => 'orders 增加 rx_no 处方号',
    'up' => function($m) {
        if (!$m->hasTable('orders')) return;
        if (!$m->hasColumn('orders', 'rx_no')) {
            $m->addColumn('orders', 'rx_no', ['type' => 'VARCHAR', 'length' => '50']);
        }
        $m->createIndex('orders', 'idx_orders_rx_no', ['rx_no']);
    }
];
