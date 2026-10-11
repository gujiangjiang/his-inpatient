<?php
// api/migrations/php/029_drug_catalog.php - 药品项目库 (门诊一体化同步缓存)
return [
    'version' => '029',
    'description' => '创建药品项目库 drug_catalog (门诊一体化同步缓存)',
    'up' => function($m) {
        if ($m->hasTable('drug_catalog')) return;
        $m->createTable('drug_catalog', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'opd_id' => ['type' => 'INTEGER'],
            'name' => ['type' => 'VARCHAR', 'length' => '200', 'notnull' => true],
            'spec' => ['type' => 'VARCHAR', 'length' => '100'],
            'unit' => ['type' => 'VARCHAR', 'length' => '20'],
            'price' => ['type' => 'REAL', 'default' => 0],
            'vendor' => ['type' => 'VARCHAR', 'length' => '100'],
            'vendor_short' => ['type' => 'VARCHAR', 'length' => '50'],
            'category' => ['type' => 'VARCHAR', 'length' => '50'],
            'form' => ['type' => 'VARCHAR', 'length' => '50'],
            'single_dose' => ['type' => 'VARCHAR', 'length' => '50'],
            'frequency_code' => ['type' => 'VARCHAR', 'length' => '20'],
            'frequency_text' => ['type' => 'VARCHAR', 'length' => '50'],
            'route' => ['type' => 'VARCHAR', 'length' => '50'],
            'is_active' => ['type' => 'INTEGER', 'default' => 1],
            'sort_no' => ['type' => 'INTEGER', 'default' => 0],
            'created_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true]);
    }
];
