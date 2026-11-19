<?php
// api/migrations/php/071_force_repair.php - 生产表结构强制修复（版本高于 070 必触发）
// 背景：生产 login/logout/logs 持续 500，疑似 migrations 表已记录高版本、
// 但 users/system_logs/audits 实际缺列（早期迁移补列未生效/被跳过）。
// 本迁移用高版本号强制 SchemaGuard 触发，对所有已知列做最终幂等补列，
// 并同时处理 users 密码锁定列——登录成功/失败路径的 UPDATE 缺列即 500。
return [
    'version' => '071',
    'description' => '强制修复: users/system_logs/audits 全部已知列幂等补齐',
    'up' => function($m) {
        /* ==================== users 锁定列（067 语义） ==================== */
        if ($m->hasTable('users')) {
            $cols = [
                'login_fail_count' => ['type' => 'INTEGER', 'default' => 0],
                'locked_at' => ['type' => 'TEXT', 'default' => ''],
                'locked_until' => ['type' => 'TEXT', 'default' => ''],
                'lock_reason' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'lock_ip' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
            ];
            foreach ($cols as $name => $def) {
                if (!$m->hasColumn('users', $name)) $m->addColumn('users', $name, $def);
            }
        }

        /* ==================== system_logs 补列（065/069 语义） ==================== */
        if ($m->hasTable('system_logs')) {
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
        }

        /* ==================== audits 补列（068/069 语义） ==================== */
        if ($m->hasTable('audits')) {
            $cols = [
                'type' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
                'ref_id' => ['type' => 'INTEGER', 'default' => 0],
                'title' => ['type' => 'VARCHAR', 'length' => '200', 'default' => ''],
                'content' => ['type' => 'TEXT', 'default' => ''],
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'pending'],
                'proposer' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'proposer_id' => ['type' => 'INTEGER', 'default' => 0],
                'handled_by' => ['type' => 'VARCHAR', 'length' => '100', 'default' => ''],
                'handled_at' => ['type' => 'TEXT'],
                'note' => ['type' => 'TEXT', 'default' => ''],
                'data_json' => ['type' => 'TEXT', 'default' => ''],
                'creation_source' => ['type' => 'VARCHAR', 'length' => '50', 'default' => ''],
            ];
            foreach ($cols as $name => $def) {
                if (!$m->hasColumn('audits', $name)) $m->addColumn('audits', $name, $def);
            }
        }
    }
];
