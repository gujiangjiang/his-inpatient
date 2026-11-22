<?php
// api/modules/nurse/execution_label.php - 药品医嘱执行贴数据（成组医嘱按根+子项完整返回）
Auth::requireRole(['admin', 'nurse']);
$orderId = (int)Validator::get('order_id', 0);
if (!$orderId) Response::error('请输入医嘱ID');

$requested = DB::selectOne("SELECT id, parent_id, patient_id FROM orders WHERE id = ?", [$orderId]);
if (!$requested) Response::error('医嘱不存在', 404);
$rootId = !empty($requested['parent_id']) ? (int)$requested['parent_id'] : (int)$requested['id'];

$order = DB::selectOne(
    "SELECT o.*, p.name AS patient_name, p.gender, p.birth_date, p.patient_no,
            p.bed_no, p.admission_no AS patient_admission_no,
            d.name AS department_name, w.name AS ward_name,
            u.name AS doctor_name, vu.name AS verified_name
     FROM orders o
     LEFT JOIN patients p ON p.id = o.patient_id
     LEFT JOIN departments d ON d.id = p.department_id
     LEFT JOIN wards w ON w.id = p.ward_id
     LEFT JOIN users u ON u.id = o.created_by
     LEFT JOIN users vu ON vu.id = o.verified_by
     WHERE o.id = ?", [$rootId]
);
if (!$order) Response::error('成组主医嘱不存在', 404);
if (!can_access_patient(Auth::user(), (int)$order['patient_id'])) Response::error('无权访问该患者医嘱', 403);
if ($order['category'] !== 'medication') Response::error('仅药品医嘱可打印执行贴');
if (!in_array($order['status'], ['verified', 'executing', 'completed'], true)) {
    Response::error('仅已核对或已执行的药品医嘱可打印执行贴');
}

$members = DB::select(
    "SELECT id, parent_id, is_group_main, group_seq, content, dosage, dosage_unit,
            usage_name, usage_rate, freq_code, freq_detail, notes, status
     FROM orders
     WHERE id = ? OR parent_id = ?
     ORDER BY CASE WHEN id = ? THEN 0 ELSE 1 END, group_seq ASC, id ASC",
    [$rootId, $rootId, $rootId]
);
$members = array_values(array_filter($members, function($item) {
    return in_array($item['status'], ['verified', 'executing', 'completed'], true);
}));
if (!$members) Response::error('该医嘱组暂无可执行药品');
$memberIds = array_map(function($item) { return (int)$item['id']; }, $members);
$ph = implode(',', array_fill(0, count($memberIds), '?'));

$dispensing = DB::selectOne(
    "SELECT dr.*, u.name AS dispensed_name
     FROM dispensing_records dr
     LEFT JOIN users u ON u.id = dr.dispensed_by
     WHERE dr.order_id IN ($ph) AND dr.status IN ('dispensed', 'delivered')
     ORDER BY dr.id DESC LIMIT 1", $memberIds
);
if (!$dispensing) Response::error('药品尚未发药，不能打印执行贴');

$execution = DB::selectOne(
    "SELECT nr.executed_at, nr.notes AS execution_notes, u.name AS executed_name
     FROM nursing_records nr
     LEFT JOIN users u ON u.id = nr.executed_by
     WHERE nr.order_id IN ($ph) AND nr.action = 'execute'
     ORDER BY nr.id DESC LIMIT 1", $memberIds
);
$order['admission_no'] = $order['patient_admission_no'] ?: $order['admission_no'];
$order['root_order_id'] = $rootId;
$order['requested_order_id'] = $orderId;
$order['group_items'] = $members;
$order['group_size'] = count($members);
$order['dispensing_status'] = $dispensing['status'];
$order['dispensed_at'] = $dispensing['dispensed_at'];
$order['dispensed_name'] = $dispensing['dispensed_name'];
$order['executed_at'] = $execution['executed_at'] ?? '';
$order['executed_name'] = $execution['executed_name'] ?? '';
$order['execution_notes'] = $execution['execution_notes'] ?? '';
unset($order['patient_admission_no']);
Response::success($order);
