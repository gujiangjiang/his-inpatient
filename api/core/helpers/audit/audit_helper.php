<?php
// api/core/helpers/audit/audit_helper.php - 统一审核流
// 用法：audit_submit($type,$refId,$title,$content,$extra)
//       audit_apply($id,$approve,$note)  → [ok, msg]
//       audit_list($status,$page,$per)    → [rows,total,has_more,pending_count]
// 状态机：pending -> approved|rejected|used；handled=关闭旧单（重提时置）

/** 提交审核记录（audits 表）；proposer 默认当前用户 */
function audit_submit($type, $refId, $title, $content, $extra = array()) {
    $u = Auth::user();
    $proposer = isset($extra['proposer']) ? $extra['proposer'] : ($u ? $u['name'] : '');
    $proposerId = isset($extra['proposer_id']) ? (int)$extra['proposer_id'] : ($u ? (int)$u['id'] : 0);
    $data = isset($extra['data']) ? $extra['data'] : null;
    $source = isset($extra['creation_source']) ? $extra['creation_source'] : '';
    $now = DateHelper::now();
    $cols = 'type, ref_id, title, content, status, proposer, proposer_id, created_at';
    $params = array($type, (int)$refId, $title, $content, 'pending', $proposer, $proposerId, $now);
    if ($data !== null) { $cols .= ', data_json'; $params[] = json_encode($data, JSON_UNESCAPED_UNICODE); }
    if ($source !== '') { $cols .= ', creation_source'; $params[] = $source; }
    DB::execute("INSERT INTO audits ($cols) VALUES (" . implode(',', array_fill(0, count($params), '?')) . ")", $params);
    return (int)DB::getPDO()->lastInsertId();
}

/** 处理审核（通过/驳回）；原子条件更新防并发；返回 [ok, msg] */
function audit_apply($id, $approve, $note = '') {
    $u = Auth::user();
    $audit = DB::selectOne("SELECT * FROM audits WHERE id = ?", [(int)$id]);
    if (!$audit) return [false, '审核记录不存在'];
    if ($audit['status'] !== 'pending') return [false, '该审核事项已被其他管理员处理，请刷新后重试'];
    $newStatus = $approve ? 'approved' : 'rejected';
    $aff = DB::execute("UPDATE audits SET status=?, handled_by=?, handled_at=?, note=? WHERE id=? AND status='pending'",
        [$newStatus, $u['name'] ? $u['name'] : 'admin', DateHelper::now(), $note, (int)$id]);
    if ($aff !== 1) return [false, '该审核事项已被其他管理员处理，请刷新后重试'];
    return [true, $approve ? '已通过' : '已驳回'];
}

/** 审核列表：status=pending|handled；分页 + 日期范围（from/to, Y-m-d） */
function audit_list($status, $page = 1, $per = 20, $from = '', $to = '') {
    if (!in_array($status, ['pending', 'handled'], true)) $status = 'pending';
    $where = "status = ?";
    $args = [$status];
    if ($status === 'handled') {
        $where = "status IN ('approved','rejected','used')";
        $args = [];
    }
    if ($from !== '') { $where .= " AND created_at >= ?"; $args[] = $from . ' 00:00:00'; }
    if ($to !== '') { $where .= " AND created_at <= ?"; $args[] = $to . ' 23:59:59'; }
    $total = (int)DB::selectOne("SELECT COUNT(*) AS c FROM audits WHERE $where", $args)['c'];
    $pendingCount = (int)DB::selectOne("SELECT COUNT(*) AS c FROM audits WHERE status='pending'")['c'];
    $rows = DB::select("SELECT * FROM audits WHERE $where ORDER BY id DESC LIMIT " . (int)$per . " OFFSET " . (($page - 1) * (int)$per), $args);
    $hasMore = $page * $per < $total;
    return ['rows' => $rows, 'total' => $total, 'has_more' => $hasMore, 'pending_count' => $pendingCount];
}
