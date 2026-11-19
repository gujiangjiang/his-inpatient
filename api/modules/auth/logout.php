<?php
// api/modules/auth/logout.php
// 退出登录必须无条件成功：任何异常（含 PHP8 Error）都不得阻断会话销毁。
try {
    Auth::init();
    $u = Auth::user();
    log_write('operation', 'login', '退出登录：' . ($u['name'] ?? ''), '', 'info');
    Auth::logout();
} catch (\Throwable $e) {
    error_log('[logout] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    // 即使记录/初始化失败，也尽力销毁会话
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_unset();
        session_destroy();
    } catch (\Throwable $e2) {
        error_log('[logout] session cleanup failed: ' . $e2->getMessage());
    }
}
Response::success();