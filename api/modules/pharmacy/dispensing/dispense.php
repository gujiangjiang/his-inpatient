<?php
// api/modules/pharmacy/dispensing/dispense.php - 中心药房摆药/发药（双药房流程）
// 摆药：verified/prepared → dispensed（自动算量 + 欠费拦截 + 扣 prepay + 扣库存 + 写流水）
// 发药送达：dispensed → delivered
Auth::requireRole(['admin', 'pharmacist']);
$action = Validator::get('action', 'queue');

// 摆药队列（中心药房：已核对或已预摆，未发药）
if ($action === 'queue') {
    $scope = Validator::get('scope', 'all');   // all 全部 / prepared 仅已预摆
    $sql = "SELECT o.id, o.content, o.dosage, o.dosage_unit, o.freq_code, o.order_period, o.dispense_status,
                   o.plan_time, o.execute_batch_no, o.start_date, o.end_date, o.base_dosage, o.drug_id,
                   p.name AS patient_name, p.bed_no, w.name AS ward_name, u.name AS doctor_name,
                   m.name AS med_name, m.stock_quantity AS stock, m.spec_pack_qty, m.spec_pack_unit, m.allow_split, m.price AS med_price
            FROM orders o
            LEFT JOIN patients p ON o.patient_id = p.id
            LEFT JOIN wards w ON p.ward_id = w.id
            LEFT JOIN users u ON o.created_by = u.id
            LEFT JOIN medications m ON (o.drug_id > 0 AND m.id = o.drug_id) OR (o.drug_id IS NULL AND m.name = o.content)
            WHERE o.category = 'medication'
              AND o.pharmacy_type = 'center'
              AND o.dispense_status IN ('none', 'prepared')
              AND o.billing_flags NOT LIKE '%不摆药%'";
    if ($scope === 'prepared') $sql .= " AND o.dispense_status = 'prepared'";
    $sql .= " ORDER BY CASE o.dispense_status WHEN 'prepared' THEN 0 ELSE 1 END, o.id ASC";
    $stmt = DB::getPDO()->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $med = [
            'spec_pack_qty' => (int)($r['spec_pack_qty'] ?? 1),
            'allow_split' => (int)($r['allow_split'] ?? 0),
            'unit' => (string)($r['spec_pack_unit'] ?? ''),
            'price' => (float)$r['med_price'],
        ];
        $order = [
            'dosage' => $r['dosage'], 'base_dosage' => $r['base_dosage'],
            'freq_code' => $r['freq_code'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
        ];
        $r['auto_qty'] = dual_auto_qty($order, $med);
        $r['auto_amount'] = round(((float)$med['price'] / dual_med_pack_qty($med)) * $r['auto_qty'], 2);
    }
    unset($r);
    Response::success($rows);
}

// 摆药扣费（单条或批量）
if ($action === 'dispense') {
    $ids = Validator::get('ids');
    if (!is_array($ids) || !$ids) Response::error('请选择要摆药的医嘱');
    $ok = 0; $fail = []; $total = 0.0; $changed = [];
    foreach (array_unique(array_map('intval', $ids)) as $oid) {
        $r = dual_dispense_order($oid, Auth::user()['id']);
        if ($r['ok']) { $ok++; $total += $r['data']['amount']; $changed[] = $oid; }
        else $fail[] = $r['msg'];
    }
    Response::success(['ok' => $ok, 'total' => round($total, 2), 'changed' => $changed, 'fail' => $fail],
        '摆药完成 ' . $ok . ' 条，扣费 ¥' . number_format($total, 2) . ($fail ? '；失败：' . implode('；', array_slice($fail, 0, 3)) : ''));
}

// 发药送达（按发药记录ID定位医嘱）
if ($action === 'deliver') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    $rec = DB::selectOne("SELECT * FROM dispensing_records WHERE id = ?", [$id]);
    if (!$rec) Response::error('发药记录不存在');
    $r = dual_deliver_order((int)$rec['order_id'], Auth::user()['id']);
    if (!$r['ok']) Response::error($r['msg']);
    log_write('operation', 'pharmacy', '发药送达', '发药记录 #' . $id, 'info', ['action' => 'deliver']);
    Response::success([], $r['msg']);
}
