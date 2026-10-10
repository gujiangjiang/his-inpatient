<?php
// api/modules/orders/group_add.php - 向草稿药品成组医嘱追加子医嘱
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$rootId = Validator::get('root_id');
$item = Validator::get('item');

if (!$rootId || !is_array($item) || empty($item['content'])) Response::error('参数不完整');

$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$rootId]);
if (!$order) Response::error('医嘱不存在');
if (!can_access_patient(Auth::user(), $order['patient_id'])) Response::error('权限不足', 403);
if ($order['status'] !== 'draft') Response::error('仅草稿医嘱可成组');

// 定位组主医嘱 (若传入子医嘱则回溯到根)
$root = $order;
if (!empty($order['parent_id'])) {
    $root = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$order['parent_id']]);
    if (!$root) Response::error('组主医嘱不存在');
}
if ($root['category'] !== 'medication') Response::error('仅药品医嘱支持成组');
if ($root['status'] !== 'draft') Response::error('仅草稿医嘱可成组');

// 排他: 组内 (含主医嘱) 已有同药品/同内容则拒绝
$members = array_merge([$root], DB::select("SELECT * FROM orders WHERE parent_id = ?", [$root['id']]));
foreach ($members as $m) {
    $sameDrug = !empty($item['drug_id']) && !empty($m['drug_id']) && $m['drug_id'] == $item['drug_id'];
    $sameContent = trim($m['content']) === trim($item['content']);
    if ($sameDrug || $sameContent) {
        Response::error('该药品已在组内, 不能重复添加');
    }
}

// 标记组主 + 追加子医嘱 (继承组主期间与用法)
$seq = 1;
foreach ($members as $m) {
    $seq = max($seq, ((int)$m['group_seq']) + 1);
}
$userId = Auth::user()['id'];
$now = DateHelper::now();
$orderNo = 'O' . date('YmdHis') . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

DB::execute("UPDATE orders SET is_group_main = 1, updated_at = ? WHERE id = ?", [$now, $root['id']]);
DB::execute(
    "INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period,
        order_no, content, category, status, drug_id, dosage, dosage_unit, usage_name, usage_rate,
        freq_code, freq_detail, billing_flags, group_seq, sort_no, start_date, created_by, created_at, updated_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    [
        $root['patient_id'], $root['admission_no'], $root['id'], 0, 'medication',
        $root['order_period'] ?: 'long',
        $orderNo, $item['content'], 'medication', 'draft',
        $item['drug_id'] ?? null, $item['dosage'] ?? '', $item['dosage_unit'] ?? '',
        $root['usage_name'] ?? '', $root['usage_rate'] ?? '',
        '', '', $item['billing_flags'] ?? '',
        $seq, 0, $root['start_date'] ?: $now, $userId, $now, $now
    ]
);
Response::success(['id' => DB::getPDO()->lastInsertId(), 'root_id' => $root['id']]);
