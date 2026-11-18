<?php
// api/modules/nurse/prepare.php - 护士站预摆药: 申请/取消 + 已核对待摆药药品医嘱列表
// 流程：医生开药 → 护士核医嘱(verified) → 本页「申请预摆药」(prepared) →
//       中心药房摆药扣费(dispensed) → 护士执行
Auth::requireRole(['admin', 'nurse']);
$action = Validator::get('action', 'list');

if ($action === 'list') {
    $user = Auth::user();
    $sql = "SELECT o.id, o.content, o.dosage, o.dosage_unit, o.freq_code, o.freq_detail, o.order_period,
                   o.dispense_status, o.plan_time, o.execute_batch_no, o.charge_amount, o.status,
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

// 申请预摆药（可批量）
if ($action === 'apply') {
    $ids = Validator::get('ids');
    if (!is_array($ids) || !$ids) Response::error('请选择要预摆药的医嘱');
    $planTime = trim((string)Validator::get('plan_time', ''));
    $ok = 0; $fail = [];
    foreach (array_unique(array_map('intval', $ids)) as $oid) {
        $r = dual_prepare_order($oid, Auth::user()['id'], $planTime);
        if ($r['ok']) $ok++; else $fail[] = $r['msg'];
    }
    Response::success(['ok' => $ok, 'fail' => $fail], '预摆药申请完成（' . $ok . ' 条）' . ($fail ? '；失败：' . implode('；', array_slice($fail, 0, 3)) : ''));
}

// 取消预摆药（护士撤回）
if ($action === 'cancel') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    DB::execute("UPDATE orders SET dispense_status = 'none', plan_time = NULL, updated_at = ?
                 WHERE id = ? AND dispense_status = 'prepared'", [DateHelper::now(), $id]);
    Response::success([], '已取消预摆药');
}
