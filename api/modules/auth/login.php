<?php
// api/modules/auth/login.php - 登录（验证码校验 + 失败锁定，参照门诊一体化）
Auth::init();

$data = Validator::all();
$username = trim((string)Validator::get('username', ''));
$password = (string)Validator::get('password', '');
$captcha = strtolower(trim((string)Validator::get('captcha', '')));
$needCaptcha = Validator::get('need_captcha', '0') === '1';

if ($username === '' || $password === '') {
    Response::error('请输入用户名和密码');
}

$mode = (string)ConfigDB::get('login.captcha_mode', 'auto');
$captchaRequired = $mode === 'force' || $needCaptcha || ($mode === 'auto' && $captcha !== '');
if ($captchaRequired) {
    $expect = isset($_SESSION['captcha_code']) ? (string)$_SESSION['captcha_code'] : '';
    unset($_SESSION['captcha_code']);   // 单次使用
    if ($expect === '' || $captcha === '' || $captcha !== $expect) {
        log_write('operation', 'login', '验证码错误：' . $username, '', 'warning', ['action' => 'captcha_fail']);
        Response::error('验证码错误，请重新输入');
    }
}

// 锁定检查（阈值从 ConfigDB 读，默认 5 次；锁定时长 15 分钟）
$lockCount = max(3, min(10, (int)ConfigDB::get('login.fail_lock_count', 5)));
$userRow = DB::selectOne("SELECT * FROM users WHERE username = ? LIMIT 1", [$username]);
$now = time();
if ($userRow) {
    $lockedUntil = (int)strtotime((string)$userRow['locked_until']);
    if ($userRow['lock_reason'] === 'password_error_locked' && $lockedUntil > $now) {
        $left = max(1, ceil(($lockedUntil - $now) / 60));
        Response::error('账号已锁定（密码错误超限），请 ' . $left . ' 分钟后重试', 423);
    }
}

$user = Auth::login($username, $password);
if (!$user) {
    if ($userRow) {
        $fails = (int)$userRow['login_fail_count'] + 1;
        $update = "UPDATE users SET login_fail_count = ? WHERE id = ?";
        $params = [$fails, $userRow['id']];
        if ($fails >= $lockCount) {
            $params = [$fails, $userRow['id'], gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', $now + 15 * 60),
                'password_error_locked', $_SERVER['REMOTE_ADDR'] ?? ''];
            $update = "UPDATE users SET login_fail_count=?, locked_at=?, locked_until=?, lock_reason=?, lock_ip=? WHERE id=?";
            // 参数顺序：fails, at, until, reason, ip, id
            $params = [$fails, gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', $now + 15 * 60), 'password_error_locked', $_SERVER['REMOTE_ADDR'] ?? '', $userRow['id']];
        }
        DB::execute($update, $params);
        log_write('operation', 'login', '登录失败：' . $username . '（' . $fails . '/' . $lockCount . '）', '', 'warning');
    }
    Response::error('用户名或密码错误');
}

// 登录成功：清零失败计数与锁定
DB::execute("UPDATE users SET login_fail_count=0, locked_at='', locked_until='', lock_reason='', lock_ip='' WHERE id=?", [$user['id']]);
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
