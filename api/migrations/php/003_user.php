<?php
// api/migrations/php/003_user.php - 用户
return [
    'version' => '003',
    'description' => '创建用户表',
    'up' => function($m) {
        if ($m->hasTable('users')) return;
        $m->createTable('users', [
            'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
            'password' => ['type' => 'VARCHAR', 'length' => '256', 'notnull' => true],
            'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
            'role' => ['type' => 'VARCHAR', 'length' => '20', 'notnull' => true],
            'department_id' => ['type' => 'INTEGER'],
            'ward_id' => ['type' => 'INTEGER'],
            'is_active' => ['type' => 'INTEGER', 'default' => 1],
            'created_at' => ['type' => 'TEXT'],
        ], ['if_not_exists' => true, 'foreign_keys' => [
            ['column' => 'department_id', 'references' => 'departments', 'on' => 'id'],
            ['column' => 'ward_id', 'references' => 'wards', 'on' => 'id']
        ]]);
        if ($m->getDriver() !== 'postgresql') {
            $m->execute("CREATE UNIQUE INDEX idx_users_username ON users(username)");
        }
    }
];
