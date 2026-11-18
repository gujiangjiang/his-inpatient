<?php
// api/modules/admin/cache_center.php - 缓存与性能（参照门诊一体化 sysinfo cache）
// action=status|flush
Auth::requireRole(['admin']);
$action = Validator::get('action', 'status');

if ($action === 'status') {
    Response::success([
        'session_dir' => (string)(ini_get('session.save_path') ?: DATA_DIR . '/session'),
        'dict_db' => dict_db_path(),
        'brand_revision' => (string)ConfigDB::get('hospital_brand_revision', ''),
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
    ]);
}

if ($action === 'flush') {
    $target = Validator::get('target', 'session');
    $done = [];
    if ($target === 'session' || $target === 'all') {
        $dir = ini_get('session.save_path');
        if ($dir && is_dir($dir)) {
            foreach (glob($dir . '/sess_*') ?: [] as $f) @unlink($f);
            $done[] = '会话缓存';
        }
    }
    if ($target === 'brand' || $target === 'all') {
        $rev = (int)ConfigDB::get('hospital_brand_revision', 0);
        ConfigDB::set('hospital_brand_revision', (string)($rev + 1));
        $done[] = '品牌缓存';
    }
    if ($target === 'dict' || $target === 'all') {
        $done[] = '字典缓存（WAL 自维护）';
    }
    if (!$done) $done[] = '无操作';
    log_write('operation', 'config', '清缓存：' . implode('、', $done), '', 'info', ['action' => 'cache_flush']);
    Response::success(['done' => $done], '已清理：' . implode('、', $done));
}