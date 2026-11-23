<?php
// api/modules/orders/order_update.php - 行内修改草稿医嘱
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$id = Validator::get('id');
if (!$id) Response::error('缺少医嘱ID');

$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');
if ($order['status'] !== 'draft') Response::error('仅草稿医嘱可修改');

// 权限: 须可访问该患者 (admin / 授权科室 / 指派管辖)
$user = Auth::user();
if (!can_access_patient($user, $order['patient_id'])) Response::error('权限不足', 403);

$fields = ['content', 'dosage', 'dosage_unit', 'usage_name', 'usage_rate',
           'freq_code', 'freq_detail', 'billing_flags', 'order_period',
           'start_date', 'notes', 'duration'];

// 生效期间: 请求里带 order_period 以请求为准
$effectivePeriod = $order['order_period'] ?: 'long';
$reqPeriod = Validator::get('order_period');
if (in_array($reqPeriod, ['long', 'temp'])) $effectivePeriod = $reqPeriod;

$updates = [];
$params = [];
foreach ($fields as $f) {
    $v = Validator::get($f);
    if ($v !== null) {
        if ($f === 'content' && trim((string)$v) === '') Response::error('医嘱内容不能为空');
        if ($f === 'order_period' && !in_array($v, ['long', 'temp'])) continue;
        // 开嘱时间不能早于患者入科时间
        if ($f === 'start_date' && trim((string)$v) !== '') {
            // 入科时间为存储时间列，模式感知换算后与本地墙钟开嘱时间比较
            $admittedAt = patient_admitted_at($order['patient_id']);
            $admittedTs = TimeHelper::ts($admittedAt);
            if ($admittedTs !== null && strtotime($v) < $admittedTs) {
                Response::error('开嘱时间不能早于患者入科时间');
            }
        }
        // 临时医嘱禁止写频次/执行时间
        if ($effectivePeriod === 'temp' && in_array($f, ['freq_code', 'freq_detail'])) continue;
        $updates[] = "$f = ?";
        $params[] = $v;
    }
}
// 切换到临时医嘱: 强制清空频次与执行时间
if ($effectivePeriod === 'temp') {
    $updates[] = "freq_code = ''";
    $updates[] = "freq_detail = ''";
}
if (empty($updates)) Response::error('没有需要更新的字段');

$updates[] = "updated_at = ?";
$params[] = DateHelper::now();
$params[] = $id;

DB::execute("UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?", $params);
Response::success(['id' => $id]);
