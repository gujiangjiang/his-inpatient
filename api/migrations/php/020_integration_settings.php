<?php
// api/migrations/php/020_integration_settings.php - 集成连接配置 (门诊一体化 / PACS)
return [
    'version' => '020',
    'description' => '创建集成连接配置表 integration_settings',
    'up' => function($m) {
        if ($m->hasTable('integration_settings')) return;
        $m->createTable('integration_settings', [
            'ckey' => ['type' => 'VARCHAR', 'length' => '100', 'primary' => true],
            'cvalue' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true]);
    }
];
