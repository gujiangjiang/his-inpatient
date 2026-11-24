<?php
// api/modules/nurse/order/prepare.php - 护士站预摆药: 按组批量申请/取消 + 待摆药药品医嘱列表
// 流程：医生开药 → 护士核医嘱(verified) → 本页「批量预摆药」(prepared, 欠费/库存前置校验) →
//       中心药房摆药扣费(dispensed) → 护士执行
// 组合医嘱整组预摆: 根+子一起申请, 任一药品库存不足则整组失败并指明具体药品
Auth::requireRole(['admin', 'nurse']);
$action = Validator::get('action', 'list');

/** 组内医嘱 (根 + 子按组序), 不存在返回空数组 */
function prepare_group_orders($rootId) {
    $root = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$rootId]);
    if (!$root) return [];
    $subs = DB::select("SELECT * FROM orders WHERE parent_id = ? ORDER BY group_seq, id", [$rootId]);
    return array_merge([$root], $subs);
}

/** 单条医嘱库存预检: 返回 null=通过, string=具体原因 (药品匹配失败/库存不足) */
function prepare_stock_issue($order) {
    $med = null;
    if ((int)$order['drug_id'] > 0) {
        $med = DB::selectOne("SELECT * FROM medications WHERE id = ?", [$order['drug_id']]);
    }
    if (!$med) {
        $med = DB::selectOne("SELECT * FROM medications WHERE name = ? AND is_active = 1 ORDER BY id LIMIT 1", [$order['content']]);
    }
    if (!$med) return '未找到对应中心药房药品（' . $order['content'] . '）';
    $qty = dual_auto_qty($order, $med);
    if ((int)$med['stock_quantity'] < $qty) {
        return $med['name'] . '（需要 ' . $qty . dual_med_unit($med) . '，当前 ' . (int)$med['stock_quantity'] . '）';
    }
    return null;
}

if ($action === 'list') {
    $user = Auth::user();
    $sql = "SELECT o.id, o.parent_id, o.content, o.dosage, o.dosage_unit, o.freq_code, o.freq_detail, o.order_period,
                   o.dispense_status, o.plan_time, o.execute_batch_no, o.charge_amount, o.status, o.patient_id,
                   p.name AS patient_name, p.bed_no, p.ward_id, w.name AS ward_name,
                   u.name AS doctor_name
            FROM orders o
            LEFT JOIN patients p ON o.patient_id = p.id
            LEFT JOIN wards w ON p.ward_id = w.id
            LEFT JOIN users u ON o.created_by = u.id
            WHERE o.category = 'medication'
              AND o.status = 'verified'
              AND o.pharmacy_type = 'center'
              AND o.dispense_status IN ('none', 'prepared')
              AND o.billing_flags NOT LIKE '%不摆药%'";
    $params = [];
    if ($user['role'] === 'nurse' && $user['ward_id']) {
        $sql .= " AND p.ward_id = ?";
        $params[] = $user['ward_id'];
    }
    $sql .= " ORDER BY CASE o.dispense_status WHEN 'prepared' THEN 0 ELSE 1 END, o.id ASC";
    $stmt = DB::getPDO()->prepare($sql);
    $stmt->execute($params);
    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 申请预摆药（按组整组申请: 传子医嘱 id 自动上溯整组; 欠费/库存前置校验, 失败原因明确）
if ($action === 'apply') {
    $ids = Validator::get('ids');
    if (!is_array($ids) || !$ids) Response::error('请选择要预摆药的医嘱');
    $planTime = trim((string)Validator::get('plan_time', ''));
    $userId = Auth::user()['id'];

    /* 展开为根医嘱集合: 传入子医嘱 id 时上溯根医嘱, 组合医嘱根+子一起预摆 */
    $rootIds = [];
    foreach (array_unique(array_map('intval', $ids)) as $oid) {
        $o = DB::selectOne("SELECT id, parent_id FROM orders WHERE id = ?", [$oid]);
        if ($o) $rootIds[(int)($o['parent_id'] ?: $o['id'])] = true;
    }

    $ok = 0; $fail = [];
    foreach (array_keys($rootIds) as $rootId) {
        $orders = prepare_group_orders($rootId);
        if (!$orders) { $fail[] = '医嘱 #' . $rootId . ' 不存在'; continue; }

        if (!can_access_patient(Auth::user(), (int)$orders[0]['patient_id'])) {
            $fail[] = '无权操作该患者的医嘱'; continue;
        }

        /* 欠费前置校验: 患者已欠费 (可用余额 < 0) 时禁止预摆药 */
        $funds = patient_funds((int)$orders[0]['patient_id']);
        if ($funds['balance'] < 0) {
            $fail[] = '患者已欠费（可用余额 ¥' . number_format($funds['balance'], 2) . '），请先补交费用';
            continue;
        }

        /* 逐条校验: 待核对/非药品明确指出; 库存预检整组汇总, 指明具体哪个药没货 */
        $statusIssues = []; $stockIssues = [];
        foreach ($orders as $o) {
            if ($o['category'] !== 'medication' || stripos((string)$o['billing_flags'], '不摆药') !== false) continue;
            if ($o['status'] !== 'verified') { $statusIssues[] = '「' . $o['content'] . '」尚未核对'; continue; }
            if (in_array($o['dispense_status'], ['prepared', 'dispensed', 'delivered'], true)) continue;
            $issue = prepare_stock_issue($o);
            if ($issue !== null) $stockIssues[] = $issue;
        }
        if ($statusIssues) { $fail[] = '医嘱未核对：' . implode('；', $statusIssues); continue; }
        if ($stockIssues) { $fail[] = '库存不足：' . implode('；', $stockIssues); continue; }

        /* 整组置 prepared (同一批次号; 仅 none → prepared, 已预摆/已发药的不动) */
        $batchNo = 'PRE' . date('YmdHis') . mt_rand(10, 99);
        $now = DateHelper::now();
        foreach ($orders as $o) {
            if ($o['dispense_status'] !== 'none') continue;
            if ($o['category'] !== 'medication' || stripos((string)$o['billing_flags'], '不摆药') !== false) continue;
            DB::execute("UPDATE orders SET dispense_status = 'prepared', plan_time = ?, execute_batch_no = ?, updated_at = ?
                         WHERE id = ? AND dispense_status = 'none'", [$planTime, $batchNo, $now, $o['id']]);
        }
        $ok++;
    }
    Response::success(['ok' => $ok, 'fail' => $fail],
        '预摆药申请完成（' . $ok . ' 组）' . ($fail ? '，' . count($fail) . ' 组未通过校验' : ''));
}

// 取消预摆药（护士撤回; 组合医嘱整组撤回: 子医嘱随根级联）
if ($action === 'cancel') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    DB::execute("UPDATE orders SET dispense_status = 'none', plan_time = NULL, updated_at = ?
                 WHERE (id = ? OR parent_id = ?) AND dispense_status = 'prepared'",
        [DateHelper::now(), $id, $id]);
    Response::success([], '已取消预摆药');
}
