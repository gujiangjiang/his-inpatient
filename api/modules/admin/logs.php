<?php
// api/modules/admin/logs.php - 日志中心
// action=list|latest|clear|meta|server|server-clear
Auth::requireRole(['admin']);
$action = Validator::get('action', 'list');

if ($action === 'meta') {
    Response::success([
        'operation_cats' => ['login' => '登录登出', 'account' => '账号管理', 'order' => '医嘱', 'pharmacy' => '药房', 'config' => '配置'],
        'interface_cats' => ['opd' => '门诊', 'pacs' => 'PACS', 'fhir' => 'FHIR', 'his' => 'HIS', 'app' => '应用'],
        'levels' => ['normal' => '普通', 'info' => '信息', 'warning' => '警告', 'error' => '错误'],
    ]);
}

if ($action === 'list') {
    $channel = Validator::get('channel', 'operation');
    $filter = [
        'category' => trim((string)Validator::get('category', '')),
        'direction' => trim((string)Validator::get('direction', '')),
        'level' => trim((string)Validator::get('level', '')),
        'date' => trim((string)Validator::get('date', '')),
        'kw' => trim((string)Validator::get('kw', '')),
        'before_id' => Validator::get('before_id', 0),
    ];
    $limit = max(1, min(100, (int)Validator::get('limit', 50) ?: 50));
    $r = log_query($channel, $filter, $limit);
    $resp = [
        'list' => $r['list'], 'has_more' => $r['has_more'],
        'counts' => log_counts($channel, $filter),
        'meta' => ['operation' => ['login' => '登录登出', 'account' => '账号管理', 'order' => '医嘱', 'pharmacy' => '药房', 'config' => '配置'],
                   'interface' => ['opd' => '门诊', 'pacs' => 'PACS', 'fhir' => 'FHIR', 'his' => 'HIS', 'app' => '应用']],
    ];
    if (!empty($r['error'])) $resp['error'] = $r['error'];
    Response::success($resp);
}

if ($action === 'latest') {
    $channel = Validator::get('channel', 'operation');
    $afterId = (int)Validator::get('after_id', 0);
    $filter = [
        'category' => trim((string)Validator::get('category', '')),
        'direction' => trim((string)Validator::get('direction', '')),
        'level' => trim((string)Validator::get('level', '')),
        'date' => trim((string)Validator::get('date', '')),
        'kw' => trim((string)Validator::get('kw', '')),
    ];
    $rows = log_latest($channel, $afterId, $filter);
    Response::success(['list' => $rows, 'counts' => log_counts($channel, $filter)]);
}

if ($action === 'clear') {
    $channel = Validator::get('channel', 'operation');
    $category = trim((string)Validator::get('category', ''));
    log_clear($channel, $category);
    Response::success([], '日志已清空');
}

if ($action === 'server') {
    // 服务器日志：直读 PHP error_log 文件（data/logs/app.log）
    $path = DATA_DIR . '/logs/app.log';
    $exists = is_file($path);
    $content = $exists ? @file_get_contents($path) : '';
    $lines = $content !== '' && $content !== false ? explode("\n", trim($content)) : [];
    $lines = array_slice($lines, -200);   // 最近 200 行
    Response::success(['exists' => $exists, 'path' => $exists ? $path : '', 'lines' => $lines]);
}
