<?php
// api/modules/orders/order_copy.php - 复制医嘱 (整组复制为新草稿)
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) Response::error('请输入医嘱ID');

$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');
if (!can_access_patient(Auth::user(), $order['patient_id'])) Response::error('权限不足', 403);

// 复制条件: 仅限手动开具医嘱, 不能复制草稿
if ($order['is_auto_generated'] == 1) {
    Response::error('自动生成的医嘱不能复制');
}
if ($order['status'] === 'draft') {
    Response::error('草稿医嘱不能复制');
}

// 组内成员: 根医嘱 + 子医嘱
$members = [$order];
if ($order['parent_id'] === null || $order['is_group_main']) {
    $members = array_merge($members, DB::select("SELECT * FROM orders WHERE parent_id = ? ORDER BY group_seq ASC, id ASC", [$order['id']]));
}

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $userId = Auth::user()['id'];

    $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period,
                order_no, content, category, status, drug_id, dosage, dosage_unit, usage_name, usage_rate,
                freq_code, freq_detail, billing_flags, group_seq, sort_no, start_date, created_by, notes, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $isGroup = count($members) > 1;
    $mainId = null;
    $newId = null;
    foreach ($members as $i => $m) {
        // 药品可用性: 库存为 0 时提示
        if ($m['drug_id']) {
            $med = DB::selectOne("SELECT name, stock_quantity FROM medications WHERE id = ?", [$m['drug_id']]);
            if ($med && $med['stock_quantity'] <= 0) {
                $pdo->rollback();
                Response::error('药品 "' . $med['name'] . '" 库存不足，无法复制');
            }
        }
        $orderNo = 'O' . date('YmdHis') . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        $stmt->execute([
            $order['patient_id'], $order['admission_no'],
            $i === 0 ? null : $mainId,
            ($isGroup && $i === 0) ? 1 : 0,
            $m['order_type'], $m['order_period'] ?: 'long',
            $orderNo, $m['content'], $m['category'], 'draft',
            $m['drug_id'], $m['dosage'], $m['dosage_unit'], $m['usage_name'], $m['usage_rate'],
            $m['freq_code'], $m['freq_detail'], $m['billing_flags'],
            $i, 0, $now, $userId, '', $now, $now
        ]);
        $new = $pdo->lastInsertId();
        if ($i === 0) { $mainId = $new; $newId = $new; }
    }

    $pdo->commit();
    Response::success(['id' => $newId, 'copied' => true, 'count' => count($members)]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('复制失败：' . $e->getMessage());
}
