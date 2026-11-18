<?php
// api/modules/auth/login.php
Auth::init();

$data = Validator::all();
$username = Validator::get('username');
$password = Validator::get('password');

if (!$username || !$password) {
    Response::error('请输入用户名和密码');
}

$user = Auth::login($username, $password);
if (!$user) {
    log_write('operation', 'login', '登录失败：' . $username, '', 'warning');
    Response::error('用户名或密码错误');
}

// 返回 session_id 用于令牌回退
$sessionId = Auth::getSessionId();
unset($user['password']);
log_write('operation', 'login', '登录成功：' . ($user['name'] ?? ''), $username, 'info', ['action' => 'login']);

Response::json([
    'status' => 'success',
    'message' => '登录成功',
    'data' => [
        'user' => $user,
        'session_id' => $sessionId
    ]
]);
