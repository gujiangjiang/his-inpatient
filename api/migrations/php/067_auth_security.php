<?php
// api/migrations/php/067_auth_security.php - 登录安全基础设施（参照门诊一体化）
// users 增加锁定字段：login_fail_count/locked_at/locked_until/lock_reason/lock_ip
// 验证码模式与失败锁定阈值存 ConfigDB（config.db）：login.captcha_mode / login.fail_lock_count
return [
    'version' => '067',
    'description' => '登录安全: 用户锁定字段 + 验证码/锁定配置',
    'up' => function($m) {
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
    }
];