<?php
// api/scripts/jobs/push_opd_orders.php - 住院检验检查单推送门诊队列重试（CLI / 定时任务）
// 用法: ~/.local/bin/frankenphp php-cli api/scripts/jobs/push_opd_orders.php
// 说明: 处理 integration_order_pushes 中待推送/失败（retry_count<5）的记录，
// 输出处理结果 JSON，可用于 systemd/cron 定时补偿。需先执行增量迁移
// （npm run migrate）创建队列表。
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/db/db.php';
require_once __DIR__ . '/../../core/helpers/time/time_helper.php';
require_once __DIR__ . '/../../core/helpers/time/date_helper.php';
require_once __DIR__ . '/../../core/helpers/opd/opd_helper.php';
require_once __DIR__ . '/../../core/helpers/opd/opd_order_push.php';

try {
    $res = opd_order_push_process(200);
    echo json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo json_encode(['processed' => 0, 'success' => 0, 'failed' => 0, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
