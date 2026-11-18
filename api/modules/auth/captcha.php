<?php
// api/modules/auth/captcha.php - 图形验证码（前端渲染 SVG，无 GD 依赖）
// GET  auth/captcha?action=gen          → 生成 4 位码存 session，返回 SVG
// GET  auth/captcha?action=check&flag=  → 嗅探是否需验证码（off/auto/force）
// 模式存 ConfigDB login.captcha_mode（off/auto/force）
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

// 生成验证码：4 位字母数字（去易混淆），存 session，返回 SVG
$mode = (string)ConfigDB::get('login.captcha_mode', 'auto');
if ($mode === 'off') {
    Response::success(['disabled' => true, 'svg' => '']);
}
$chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
$code = '';
for ($i = 0; $i < 4; $i++) $code .= $chars[mt_rand(0, strlen($chars) - 1)];
$_SESSION['captcha_code'] = strtolower($code);

$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="110" height="40" viewBox="0 0 110 40" style="border:1px solid #e8edf3;border-radius:6px;background:#f5f7fa">';
for ($i = 0; $i < 3; $i++) {
    $x1 = mt_rand(0, 110); $y1 = mt_rand(0, 40); $x2 = mt_rand(0, 110); $y2 = mt_rand(0, 40);
    $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="rgba(' . mt_rand(120, 200) . ',' . mt_rand(120, 200) . ',' . mt_rand(120, 200) . ',.6)" stroke-width="1"/>';
}
for ($i = 0; $i < 4; $i++) {
    $color = 'hsl(' . mt_rand(200, 255) . ',70%,' . mt_rand(25, 50) . '%)';
    $rot = mt_rand(-15, 15);
    $x = 14 + $i * 24;
    $y = mt_rand(26, 32);
    $svg .= '<text x="' . $x . '" y="' . $y . '" font-size="22" font-weight="700" fill="' . $color . '" transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')" font-family="Arial, sans-serif">' . $code[$i] . '</text>';
}
$svg .= '</svg>';
Response::success(['disabled' => false, 'svg' => $svg]);
