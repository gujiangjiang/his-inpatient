<?php
// api/migrations/php/017_order_catalog_group.php - 非药品目录类别 (仅搜索展示用)
return [
    'version' => '017',
    'description' => 'order_catalog 增加类别字段 catalog_group',
    'up' => function($m) {
        if (!$m->hasTable('order_catalog')) return;
        if (!$m->hasColumn('order_catalog', 'catalog_group')) {
            $m->addColumn('order_catalog', 'catalog_group', ['type' => 'VARCHAR', 'length' => '50']);
        }
    }
];
