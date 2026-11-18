<?php
// api/modules/auth/logout.php
Auth::init();
$u = Auth::user();
log_write('operation', 'login', '退出登录：' . ($u['name'] ?? ''), '', 'info');
Auth::logout();
Response::success();
