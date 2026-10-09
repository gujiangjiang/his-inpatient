<?php
// api/migrations/php/014_order_catalog.php - 非药品医嘱目录 (快速开嘱统一检索)
return [
    'version' => '014',
    'description' => '创建非药品医嘱目录表 order_catalog',
    'up' => function($m) {
        if ($m->hasTable('order_catalog')) return;
        $m->createTable('order_catalog', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'content' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
            'category' => ['type' => 'VARCHAR', 'length' => '50', 'default' => 'general'],
            'usage_name' => ['type' => 'VARCHAR', 'length' => '50'],
            'usage_rate' => ['type' => 'VARCHAR', 'length' => '50'],
            'freq_code' => ['type' => 'VARCHAR', 'length' => '20'],
            'freq_detail' => ['type' => 'VARCHAR', 'length' => '50'],
            'order_period' => ['type' => 'VARCHAR', 'length' => '10', 'default' => 'long'],
            'billing_flags' => ['type' => 'VARCHAR', 'length' => '100'],
            'sort_no' => ['type' => 'INTEGER', 'default' => 0],
            'is_active' => ['type' => 'INTEGER', 'default' => 1],
            'created_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true]);
        $m->createIndex('order_catalog', 'idx_order_catalog_content', ['content']);
    }
];
