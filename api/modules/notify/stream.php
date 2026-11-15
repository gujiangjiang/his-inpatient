<?php
// api/modules/notify/stream.php - SSE 消息推送长连接 (前端零轮询)
// 协议: event: notify → data: {unread, items:[...]} (状态指纹变化时推送; 其余时间为心跳 ": ping")
// 连接持有 ~50 秒后正常结束, 浏览器 EventSource 自动重连 (retry: 3000)
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']);
$uid = (int)Auth::user()['id'];

/* 关键: 释放会话写锁, 长连接不得阻塞同用户其他请求 */
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) session_write_close();
@set_time_limit(90);
ignore_user_abort(false);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); /* nginx: 关闭缓冲 */
while (ob_get_level()) { @ob_end_clean(); }
echo "retry: 3000\n\n";
flush();

$deadline = time() + 50;
$fingerprint = '';
while (time() < $deadline && !connection_aborted()) {
    try {
        $data = notify_list($uid);
    } catch (Exception $e) {
        break;
    }
    $fp = md5(json_encode([$data['unread'], array_map(function ($x) { return [$x['id'], $x['is_read'], $x['title']]; }, $data['items'])]));
    if ($fp !== $fingerprint) {
        $fingerprint = $fp;
        echo "event: notify\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo ": ping\n\n"; /* 心跳保活 (2 秒; 极小流量) */
    }
    flush();
    sleep(2);
}
echo "event: bye\ndata: {}\n\n";
flush();
