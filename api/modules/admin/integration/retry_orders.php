<?php
// api/modules/admin/integration_retry_orders.php - 重推住院检验检查单到门诊（手动补偿）
Auth::requireRole(['admin']);
$res = opd_order_push_process(200, true);   // 手动重推：忽略重试次数上限
$msg = '已重推：成功 ' . $res['success'] . ' 条'
    . ($res['failed'] > 0 ? '，失败 ' . $res['failed'] . ' 条（可查看错误后再次重试）' : '');
Response::success(array_merge($res, ['counts' => opd_order_push_counts()]), $msg);
