<?php
// api/modules/auth/diag.php - 临时公开诊断接口（排查生产 500，定位后移除）
// 仅输出表结构/迁移版本等非敏感信息，供排查 login/logout/logs 500。
$out = [
    'php' => PHP_VERSION,
    'db_type' => defined('DB_TYPE') ? DB_TYPE : '',
    'migrations' => [],
    'users_cols' => [],
    'system_logs_cols' => [],
    'audits_cols' => [],
];
try {
    foreach (DB::select("SELECT version FROM migrations ORDER BY version") as $r) {
        $out['migrations'][] = $r['version'];
    }
} catch (Exception $e) {
    $out['migrations_err'] = $e->getMessage();
}
foreach (['users', 'system_logs', 'audits'] as $t) {
    try {
        $cols = [];
        foreach (DB::select("PRAGMA table_info(" . $t . ")") as $c) $cols[] = $c['name'];
        $out[$t . '_cols'] = $cols;
    } catch (Exception $e) {
        $out[$t . '_err'] = $e->getMessage();
    }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
