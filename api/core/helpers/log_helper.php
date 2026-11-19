<?php
// api/core/helpers/log_helper.php - 日志中心核心（参照门诊一体化 LogService）
// 通道：operation（操作）/ interface（接口）；级别：normal/info/warning/error
// 服务器日志直读 PHP 日志文件，不入本表（log_server 系列单独处理）。

/** 写日志（自动补当前用户上下文 + 客户端 IP） */
function log_write($channel, $category, $summary, $detail = '', $level = 'info', $extra = []) {
    if (!in_array($channel, ['operation', 'interface'], true)) $channel = 'operation';
    if (!in_array($level, ['normal', 'info', 'warning', 'error'], true)) $level = 'info';
    $user = null;
    if (function_exists('Auth') && class_exists('Auth')) {
        try { $user = Auth::user(); } catch (\Throwable $e) { $user = null; }
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    // 日志写入失败不得阻断业务（如 system_logs 表结构异常/库锁定时），
    // 尤其退出登录等关键流程必须放行
    try {
        DB::execute("INSERT INTO system_logs (channel, category, direction, action, level, summary, detail, payload, user_id, username, role, target, remote_ip, user_agent, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$channel, $category, $extra['direction'] ?? '', $extra['action'] ?? '', $level, $summary, $detail,
             $extra['payload'] ?? '', $user['id'] ?? 0, $user['name'] ?? '', $user['role'] ?? '',
             $extra['target'] ?? '', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', DateHelper::now()]);
        // 保留期清理（轻量：每日首写清一次）
        log_maintain_once();
    } catch (\Throwable $e) {
        // 吞掉：日志非关键路径
    }
}

/** 日志保留维护（每日一次：按天数/行数上限清理） */
function log_maintain_once() {
    $cacheFile = DATA_DIR . '/log_maintain_day';
    $today = date('Y-m-d');
    if (@file_get_contents($cacheFile) === $today) return;
    @file_put_contents($cacheFile, $today);
    try {
        $days = (int)(ConfigDB::get('log.retention_days', 7) ?? 7);
        if ($days > 0) {
            DB::execute("DELETE FROM system_logs WHERE created_at < datetime('now', ?)", ['-' . $days . ' days']);
        }
    } catch (\Throwable $e) {
        // 清理失败不影响本次写入
    }
}

/** 查询日志：channel 必填，filter {category,direction,level,date,kw,before_id,after_id} */
function log_query($channel, $filter = [], $limit = 50) {
    if (!in_array($channel, ['operation', 'interface'], true)) $channel = 'operation';
    try {
        $sql = "SELECT * FROM system_logs WHERE channel = ?";
        $args = [$channel];
        $where = log_filter_where($filter);
        if ($where[0]) { $sql .= $where[0]; $args = array_merge($args, $where[1]); }
        if (!empty($filter['before_id'])) { $sql .= " AND id < ?"; $args[] = (int)$filter['before_id']; }
        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit;
        $rows = DB::select($sql, $args);
        $hasMore = count($rows) === (int)$limit;
        return ['list' => $rows, 'has_more' => $hasMore];
    } catch (\Throwable $e) {
        return ['list' => [], 'has_more' => false, 'error' => '日志查询失败：' . $e->getMessage()];
    }
}

/** 增量日志（实时轮询：id > after_id 正序） */
function log_latest($channel, $afterId, $filter = [], $limit = 50) {
    if (!in_array($channel, ['operation', 'interface'], true)) $channel = 'operation';
    try {
        $sql = "SELECT * FROM system_logs WHERE channel = ? AND id > ?";
        $args = [$channel, (int)$afterId];
        $where = log_filter_where($filter);
        if ($where[0]) { $sql .= $where[0]; $args = array_merge($args, $where[1]); }
        $sql .= " ORDER BY id ASC LIMIT " . (int)$limit;
        return DB::select($sql, $args);
    } catch (\Throwable $e) {
        return [];
    }
}

/** 构造日志查询 WHERE 片段（不含 id 游标） */
function log_filter_where($filter) {
    $sql = '';
    $args = [];
    if (!empty($filter['category'])) { $sql .= " AND category = ?"; $args[] = $filter['category']; }
    if (!empty($filter['direction'])) { $sql .= " AND direction = ?"; $args[] = $filter['direction']; }
    if (!empty($filter['level'])) { $sql .= " AND level = ?"; $args[] = $filter['level']; }
    if (!empty($filter['date'])) { $sql .= " AND created_at LIKE ?"; $args[] = $filter['date'] . '%'; }
    if (!empty($filter['kw'])) {
        $sql .= " AND (summary LIKE ? OR detail LIKE ? OR action LIKE ? OR username LIKE ? OR target LIKE ?)";
        $like = '%' . $filter['kw'] . '%';
        foreach ([0, 1, 2, 3, 4] as $i) $args[] = $like;
    }
    return [$sql, $args];
}

/** 分类计数（左侧导航徽章） */
function log_counts($channel, $filter = []) {
    if (!in_array($channel, ['operation', 'interface'], true)) return [];
    try {
        $where = log_filter_where($filter);
        $sql = "SELECT category, COUNT(*) AS cnt FROM system_logs WHERE channel = ?" . $where[0] . " GROUP BY category";
        $args = array_merge([$channel], $where[1]);
        $out = [];
        foreach (DB::select($sql, $args) as $r) $out[$r['category']] = (int)$r['cnt'];
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/** 清空日志（channel 必填；category 可空=全清） */
function log_clear($channel, $category = '') {
    if (!in_array($channel, ['operation', 'interface'], true)) return;
    if ($category !== '') {
        DB::execute("DELETE FROM system_logs WHERE channel = ? AND category = ?", [$channel, $category]);
    } else {
        DB::execute("DELETE FROM system_logs WHERE channel = ?", [$channel]);
    }
}
