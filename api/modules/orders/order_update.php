<?php
// api/modules/orders/order_update.php - 行内修改草稿医嘱
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$id = Validator::get('id');
if (!$id) Response::error('缺少医嘱ID');

$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');
if ($order['status'] !== 'draft') Response::error('仅草稿医嘱可修改');

// 权限: 医生须为本科室或指派管辖患者
$user = Auth::user();
if ($user['role'] === 'doctor') {
    $allowed = DB::selectOne(
        "SELECT p.id FROM patients p
         LEFT JOIN patient_assignments pa ON pa.patient_id = p.id
         WHERE p.id = ? AND (p.department_id = ? OR pa.attending_doctor_id = ? OR pa.senior_doctor_id = ?)",
        [$order['patient_id'], $user['department_id'], $user['id'], $user['id']]
    );
    if (!$allowed) Response::error('权限不足', 403);
}

$fields = ['content', 'dosage', 'dosage_unit', 'usage_name', 'usage_rate',
           'freq_code', 'freq_detail', 'billing_flags', 'order_period'];
$updates = [];
$params = [];
foreach ($fields as $f) {
    $v = Validator::get($f);
    if ($v !== null) {
        if ($f === 'content' && trim((string)$v) === '') Response::error('医嘱内容不能为空');
        if ($f === 'order_period' && !in_array($v, ['long', 'temp'])) continue;
        $updates[] = "$f = ?";
        $params[] = $v;
    }
}
if (empty($updates)) Response::error('没有需要更新的字段');

$updates[] = "updated_at = ?";
$params[] = DateHelper::now();
$params[] = $id;

DB::execute("UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?", $params);
Response::success(['id' => $id]);
