<?php
// api/core/helpers/notify_helper.php - 全局消息推送 (业务事件 → 定向通知; SSE 推送, 前端零轮询)
// 使用: notify_send([...]) 发送; notify_resolve(refType, refId) 业务处理完成后消除相关通知;
//       notify_orders_sync(patientId) 按患者医嘱待核状态重建/清除护士通知;
//       notify_list/notify_fetch_after/notify_unread_count 供列表与 SSE 增量使用。
// 存储: 主业务库 app_notifications (迁移 056; 懒建表兜底)

/** 懒创建通知表 (首次使用即可用) */
function notify_schema_ensure()
{
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;
    try {
        $exists = DB::selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='app_notifications'");
        if (!$exists) {
            DB::getPDO()->exec("CREATE TABLE IF NOT EXISTS app_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type VARCHAR(30) NOT NULL,
                title VARCHAR(200) NOT NULL, body TEXT, link VARCHAR(200), ref_type VARCHAR(30), ref_id INTEGER,
                meta TEXT, is_read INTEGER DEFAULT 0, created_at TEXT)");
        }
    } catch (Exception $e) { /* ignore */ }
}

/** 接收人解析: roles + ward_id (病区护士) ; 返回用户 id 列表 */
function notify_recipients(array $roles, $wardId = null)
{
    $ids = [];
    foreach ($roles as $role) {
        if ($wardId) {
            $rows = DB::select("SELECT id FROM users WHERE role = ? AND is_active = 1 AND ward_id = ?", [$role, $wardId]);
            if (!$rows) $rows = DB::select("SELECT id FROM users WHERE role = ? AND is_active = 1", [$role]);
        } else {
            $rows = DB::select("SELECT id FROM users WHERE role = ? AND is_active = 1", [$role]);
        }
        foreach ($rows as $r) $ids[] = (int)$r['id'];
    }
    return array_values(array_unique($ids));
}

/**
 * 发送通知
 * opts: user_ids[]|roles[] 接收人; ward_id 病区偏好; type/title/body/link; ref_type/ref_id; meta[]
 *       exclude_ids[] 排除 (如操作者本人); upsert=true 按 (user,ref_type,ref_id) 更新而非重复发送
 */
function notify_send(array $opts)
{
    notify_schema_ensure();
    $ids = isset($opts['user_ids']) ? array_map('intval', $opts['user_ids']) : [];
    if (!empty($opts['roles'])) $ids = array_merge($ids, notify_recipients($opts['roles'], $opts['ward_id'] ?? null));
    $exclude = array_map('intval', $opts['exclude_ids'] ?? []);
    $ids = array_values(array_diff(array_unique($ids), $exclude));
    if (!$ids) return 0;
    $now = DateHelper::now();
    $count = 0;
    foreach ($ids as $uid) {
        if (!empty($opts['upsert']) && !empty($opts['ref_type']) && !empty($opts['ref_id'])) {
            $row = DB::selectOne("SELECT id FROM app_notifications WHERE user_id = ? AND ref_type = ? AND ref_id = ?", [$uid, $opts['ref_type'], $opts['ref_id']]);
            if ($row) {
                DB::execute(
                    "UPDATE app_notifications SET title = ?, body = ?, link = ?, meta = ?, is_read = 0, created_at = ? WHERE id = ?",
                    [(string)($opts['title'] ?? ''), (string)($opts['body'] ?? ''), (string)($opts['link'] ?? ''), json_encode($opts['meta'] ?? [], JSON_UNESCAPED_UNICODE), $now, $row['id']]
                );
                $count++;
                continue;
            }
        }
        DB::insert('app_notifications', [
            'user_id' => $uid,
            'type' => (string)($opts['type'] ?? 'system'),
            'title' => (string)($opts['title'] ?? ''),
            'body' => (string)($opts['body'] ?? ''),
            'link' => (string)($opts['link'] ?? ''),
            'ref_type' => $opts['ref_type'] ?? null,
            'ref_id' => $opts['ref_id'] ?? null,
            'meta' => json_encode($opts['meta'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_read' => 0,
            'created_at' => $now,
        ]);
        $count++;
    }
    return $count;
}

/** 业务处理完成 → 消除相关通知 (如审核通过/驳回后管理员侧待办消失; 操作者处理的待办立即消失) */
function notify_resolve($refType, $refId)
{
    notify_schema_ensure();
    DB::execute("DELETE FROM app_notifications WHERE ref_type = ? AND ref_id = ?", [(string)$refType, (int)$refId]);
}

/**
 * 医嘱待核通知同步 (按患者):
 * 重建护士通知为当前「待核对(pending)」医嘱集合; 全部处理完 → 通知自动消失;
 * 已存在通知原地更新 (不重复堆积); 护士按病区匹配 (无匹配回退全部护士)。
 */
function notify_orders_sync($patientId)
{
    notify_schema_ensure();
    $patient = DB::selectOne("SELECT id, name, ward_id FROM patients WHERE id = ?", [(int)$patientId]);
    if (!$patient) return;
    $ids = array_map('intval', array_column(
        DB::select("SELECT id FROM orders WHERE patient_id = ? AND status = 'pending'", [(int)$patientId]),
        'id'
    ));
    $existing = DB::select("SELECT * FROM app_notifications WHERE ref_type = 'orders_pending' AND ref_id = ?", [(int)$patientId]);
    if (!$ids) {
        foreach ($existing as $row) DB::execute("DELETE FROM app_notifications WHERE id = ?", [$row['id']]);
        return;
    }
    $nurseIds = notify_recipients(['nurse'], $patient['ward_id'] ? (int)$patient['ward_id'] : null);
    $title = '新医嘱待核对 · ' . $patient['name'] . '（' . count($ids) . ' 条）';
    $meta = json_encode(['order_ids' => $ids], JSON_UNESCAPED_UNICODE);
    $now = DateHelper::now();
    foreach ($nurseIds as $uid) {
        $hit = null;
        foreach ($existing as $row) {
            if ((int)$row['user_id'] === (int)$uid) { $hit = $row; break; }
        }
        if ($hit) {
            DB::execute("UPDATE app_notifications SET title = ?, meta = ?, is_read = 0, created_at = ? WHERE id = ?", [$title, $meta, $now, $hit['id']]);
        } else {
            DB::insert('app_notifications', [
                'user_id' => $uid, 'type' => 'order', 'title' => $title, 'body' => '点击进入核查清单处理',
                'link' => '#nurse/checklist', 'ref_type' => 'orders_pending', 'ref_id' => (int)$patientId,
                'meta' => $meta, 'is_read' => 0, 'created_at' => $now,
            ]);
        }
    }
}

/** 未读数 */
function notify_unread_count($userId)
{
    notify_schema_ensure();
    $row = DB::selectOne("SELECT COUNT(*) AS n FROM app_notifications WHERE user_id = ? AND is_read = 0", [(int)$userId]);
    return (int)($row['n'] ?? 0);
}

/** 最新通知列表 (含未读数); $limit 条 */
function notify_list($userId, $limit = 50)
{
    notify_schema_ensure();
    $rows = DB::select(
        "SELECT id, type, title, body, link, ref_type, ref_id, is_read, created_at FROM app_notifications
          WHERE user_id = ? ORDER BY id DESC LIMIT " . max(1, min(200, (int)$limit)),
        [(int)$userId]
    );
    foreach ($rows as &$r) {
        $r['is_read'] = (int)$r['is_read'];
    }
    unset($r);
    return ['unread' => notify_unread_count($userId), 'items' => $rows];
}

/** 增量拉取 (SSE 首帧/重连用): id > afterId */
function notify_fetch_after($userId, $afterId)
{
    notify_schema_ensure();
    return DB::select(
        "SELECT id, type, title, body, link, ref_type, ref_id, is_read, created_at FROM app_notifications
          WHERE user_id = ? AND id > ? ORDER BY id ASC LIMIT 100",
        [(int)$userId, (int)$afterId]
    );
}

/** 标记已读 (ids 数组或 all=1) */
function notify_mark_read($userId, array $ids = [], $all = false)
{
    notify_schema_ensure();
    if ($all) {
        DB::execute("UPDATE app_notifications SET is_read = 1 WHERE user_id = ?", [(int)$userId]);
        return;
    }
    foreach ($ids as $id) {
        DB::execute("UPDATE app_notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [(int)$id, (int)$userId]);
    }
}
