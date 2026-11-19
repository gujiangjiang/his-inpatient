<?php
// api/migrations/php/065_system_logs.php - 系统日志中心
// 统一日志表 system_logs：操作日志 + 接口日志 + 服务器日志（服务器日志直读文件不入表）
// 通道：operation（操作）/ interface（接口）；级别：normal/info/warning/error
return [
    'version' => '065',
    'description' => '日志中心: system_logs 表 + 日志保留配置',
    'up' => function($m) {
        if ($m->hasTable('system_logs')) {
            // 表已存在：幂等补齐列（早期建表可能缺列，SELECT * 用到即 500）
            $cols = [
                'channel' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'operation'],
                'category' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
                'direction' => ['type' => 'VARCHAR', 'length' => '10', 'default' => ''],
                'action' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'level' => ['type' => 'VARCHAR', 'length' => '10', 'default' => 'info'],
                'summary' => ['type' => 'TEXT', 'default' => ''],
                'detail' => ['type' => 'TEXT', 'default' => ''],
                'payload' => ['type' => 'TEXT', 'default' => ''],
                'user_id' => ['type' => 'INTEGER', 'default' => 0],
                'username' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'role' => ['type' => 'VARCHAR', 'length' => '20', 'default' => ''],
                'target' => ['type' => 'VARCHAR', 'length' => '200', 'default' => ''],
                'remote_ip' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
                'user_agent' => ['type' => 'VARCHAR', 'length' => '200', 'default' => ''],
                'created_at' => ['type' => 'TEXT'],
            ];
            foreach ($cols as $name => $def) {
                if (!$m->hasColumn('system_logs', $name)) $m->addColumn('system_logs', $name, $def);
            }
            return;
        }
        $m->createTable('system_logs', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'channel' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'operation'], // operation|interface
                'category' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
                'direction' => ['type' => 'VARCHAR', 'length' => '10', 'default' => ''],        // inbound|outbound
                'action' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'level' => ['type' => 'VARCHAR', 'length' => '10', 'default' => 'info'],        // normal|info|warning|error
                'summary' => ['type' => 'TEXT', 'default' => ''],
                'detail' => ['type' => 'TEXT', 'default' => ''],
                'payload' => ['type' => 'TEXT', 'default' => ''],
                'user_id' => ['type' => 'INTEGER', 'default' => 0],
                'username' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'role' => ['type' => 'VARCHAR', 'length' => '20', 'default' => ''],
                'target' => ['type' => 'VARCHAR', 'length' => '200', 'default' => ''],
                'remote_ip' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
                'user_agent' => ['type' => 'VARCHAR', 'length' => '200', 'default' => ''],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
            $m->createIndex('system_logs', 'idx_system_logs_channel', ['channel', 'category', 'id']);
            $m->createIndex('system_logs', 'idx_system_logs_created', ['created_at']);
        }
];