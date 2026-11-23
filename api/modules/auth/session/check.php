<?php
// api/modules/auth/session/check.php
Auth::init();

$user = Auth::user();
if ($user) {
    unset($user['password']);
    Response::success($user);
} else {
    /* 登录页启动时的正常状态探测: 未登录不是接口异常, 避免浏览器控制台产生预期 401 */
    Response::success(null, '未登录');
}
