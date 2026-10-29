<?php
// api/modules/setup/admin.php - 向导第 5 步：创建管理员账号
require_once __DIR__ . '/_common.php';
setup_require_open();
setup_require_stage(array('admin', 'done'));

$username = trim((string)Validator::get('username', ''));
$password = (string)Validator::get('password', '');
$password2 = (string)Validator::get('password2', '');
$name = trim((string)Validator::get('name', ''));
if ($name === '') $name = '系统管理员';

if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) Response::error('用户名需为 3-32 位字母/数字/下划线');
if (strlen($password) < 6) Response::error('密码长度至少 6 位');
if ($password !== $password2) Response::error('两次密码不一致');

// 已存在管理员则拒绝重复创建（防绕过向导重复初始化）
$exists = (int)DB::selectOne("SELECT COUNT(*) AS c FROM users WHERE role='admin'")['c'];
if ($exists > 0) Response::error('管理员已创建', 409);

DB::insert('users', array(
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'name' => $name,
    'role' => 'admin',
    'is_active' => 1,
    'created_at' => DateHelper::now(),
));

ConfigDB::set('setup.stage', 'done');
Response::success(array('stage' => 'done'), '管理员创建成功');
