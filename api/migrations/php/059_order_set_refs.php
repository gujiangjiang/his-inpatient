<?php
// 059_order_set_refs.php - 组套条目引用快照 (下架/规格变更可用性校验)
return [
    'version' => '059',
    'description' => 'order_set_items 增加引用快照 (ref_id/名称/规格/厂家)',
    'up' => function ($m) {
        if (!$m->hasTable('order_set_items')) return;
        if (!$m->hasColumn('order_set_items', 'ref_id')) $m->addColumn('order_set_items', 'ref_id', ['type' => 'INTEGER']);
        if (!$m->hasColumn('order_set_items', 'ref_name')) $m->addColumn('order_set_items', 'ref_name', ['type' => 'VARCHAR', 'length' => 200]);
        if (!$m->hasColumn('order_set_items', 'ref_spec')) $m->addColumn('order_set_items', 'ref_spec', ['type' => 'VARCHAR', 'length' => 100]);
        if (!$m->hasColumn('order_set_items', 'ref_manufacturer')) $m->addColumn('order_set_items', 'ref_manufacturer', ['type' => 'VARCHAR', 'length' => 100]);
    }
];
