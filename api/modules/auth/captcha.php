<?php
// api/modules/auth/captcha.php - 图形验证码（后端 GD 绘制 PNG）
// GET  auth/captcha?action=gen          → 生成 4 位码存 session，输出 PNG 图片
// GET  auth/captcha?action=check&flag=  → 嗅探是否需验证码（off/auto/force）
// 模式存 ConfigDB login.captcha_mode（off/auto/force）；GD 缺失降级纯文本。
Auth::init();
$action = Validator::get('action', 'gen');

if ($action === 'check') {
    $mode = (string)ConfigDB::get('login.captcha_mode', 'auto');
    $require = false;
    if ($mode === 'force') {
        $require = true;
    } elseif ($mode === 'auto') {
        $require = (int)Validator::get('flag', 0) === 1;
        if (!$require) {
            $username = trim((string)Validator::get('username', ''));
            if ($username !== '') {
                $u = DB::selectOne("SELECT login_fail_count FROM users WHERE username = ? LIMIT 1", [$username]);
                if ($u && (int)$u['login_fail_count'] >= 1) $require = true;
            }
        }
    }
    Response::success(['require_captcha' => $require]);
}

// 生成验证码图片（GD PNG）
$mode = (string)ConfigDB::get('login.captcha_mode', 'auto');
if ($mode === 'off') {
    http_response_code(204);
    exit;
}
// 4 位字符（去易混淆 0/O/1/I/l），密码学随机
$chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
$code = '';
for ($i = 0; $i < 4; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
$_SESSION['captcha_code'] = strtolower($code);
$_SESSION['captcha_created_at'] = time();

if (!function_exists('imagecreatetruecolor')) {
    // GD 缺失：纯文本兜底（校验仍走 session）
    header('Content-Type: text/plain; charset=utf-8');
    echo $code;
    exit;
}

$w = 130; $h = 42;
$img = imagecreatetruecolor($w, $h);
// 浅灰白底
$bg = imagecolorallocate($img, 245, 247, 250);
imagefill($img, 0, 0, $bg);
// 2 条浅色干扰弧线
for ($i = 0; $i < 2; $i++) {
    $c = imagecolorallocate($img, mt_rand(150, 210), mt_rand(150, 210), mt_rand(150, 210));
    imagearc($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(60, 120), mt_rand(60, 120),
        mt_rand(0, 360), mt_rand(0, 360), $c);
}
// 80 个浅色噪点
for ($i = 0; $i < 80; $i++) {
    $c = imagecolorallocate($img, mt_rand(170, 220), mt_rand(170, 220), mt_rand(170, 220));
    imagesetpixel($img, mt_rand(0, $w - 1), mt_rand(0, $h - 1), $c);
}
// 4 字符（内置字体，横纵微扰 + 随机深色）
for ($i = 0; $i < 4; $i++) {
    $c = imagecolorallocate($img, mt_rand(20, 70), mt_rand(60, 110), mt_rand(130, 190));
    $x = 12 + $i * 28 + mt_rand(-3, 3);
    $y = mt_rand(16, 28);
    imagestring($img, mt_rand(4, 6), $x, $y, $code[$i], $c);
}
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
imagepng($img);
imagedestroy($img);
exit;
