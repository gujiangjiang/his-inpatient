<?php
// api/migrations/php/019_order_base_dosage.php - 医嘱基准剂量 (倍数换算/自动计费占位)
return [
    'version' => '019',
    'description' => 'orders 增加 base_dosage 基准单次剂量',
    'up' => function($m) {
        if (!$m->hasTable('orders')) return;
        if (!$m->hasColumn('orders', 'base_dosage')) {
            $m->addColumn('orders', 'base_dosage', ['type' => 'VARCHAR', 'length' => '50']);
        }
    }
];
