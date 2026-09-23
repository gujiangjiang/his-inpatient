<?php
// api/migrations/php/001_core.php - 核心表: 系统配置
return [
    'version' => '001',
    'description' => '创建系统配置表',
    'up' => function($m) {
        if ($m->hasTable('system_config')) return;
        $m->createTable('system_config', [
            'config_key' => ['type' => 'VARCHAR', 'length' => '100', 'primary' => true],
            'config_value' => ['type' => 'TEXT'],
            'config_group' => ['type' => 'VARCHAR', 'length' => '50'],
            'description' => ['type' => 'TEXT'],
            'updated_at' => ['type' => 'TEXT'],
        ]);
        
        // 插入默认配置
        $m->execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description) VALUES ('setup_completed', '0', 'system', '系统是否已初始化')");
        $m->execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description) VALUES ('hospital_name', '', 'system', '医院名称')");
        $m->execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description) VALUES ('hospital_code', '', 'system', '组织机构代码')");
    }
];
