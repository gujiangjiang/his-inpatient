<?php
// api/modules/auth/check.php
Auth::init();

$user = Auth::user();
if ($user) {
    unset($user['password']);
    Response::success($user);
} else {
    Response::error('未授权', 401);
}
