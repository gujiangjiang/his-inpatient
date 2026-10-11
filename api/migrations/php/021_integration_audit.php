<?php
// api/migrations/php/021_integration_audit.php - 集成配置变更审计表
return [
    'version' => '021',
    'description' => '创建集成配置变更审计表 integration_audit',
    'up' => function($m) {
        if ($m->hasTable('integration_audit')) return;
        $m->createTable('integration_audit', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'actor_name' => ['type' => 'VARCHAR', 'length' => '100'],
            'area' => ['type' => 'VARCHAR', 'length' => '50'],
            'detail' => ['type' => 'TEXT'],
            'ip' => ['type' => 'VARCHAR', 'length' => '64'],
            'created_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true]);
    }
];
