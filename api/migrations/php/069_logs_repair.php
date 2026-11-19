<?php
// api/migrations/php/069_logs_repair.php - 日志/审核表结构幂等修复
// 背景：065/068 建表后，若迁移表已记录较高版本、而 system_logs/audits 早期建表
// 缺列（或字段类型不对），065 的补列分支不会重跑 → SELECT */INSERT 即 500。
// 本迁移对所有现存表做最终幂等补列 + 补索引，可安全重复执行。
return [
    'version' => '069',
    'description' => '日志/审核表幂等修复: system_logs/audits 补列补索引',
    'up' => function($m) {
        /* ==================== system_logs 幂等补列 ==================== */
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
            $m->createIndex('system_logs', 'idx_system_logs_channel', ['channel', 'category', 'id']);
            $m->createIndex('system_logs', 'idx_system_logs_created', ['created_at']);
        }

        /* ==================== audits 幂等补列 ==================== */
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
            $m->createIndex('audits', 'idx_audits_status_type', ['status', 'type']);
        }
    }
];