<?php
// api/modules/pharmacy/dispensing_save.php
Auth::requireRole(['admin', 'pharmacist']);
$data = Validator::all();
$orderId = Validator::get('order_id');
$medicationId = Validator::get('medication_id');
$quantity = Validator::get('quantity');

if (!$orderId || !$medicationId || !$quantity) Response::error('请输入完整发药信息');

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    // 校验库存
    $med = DB::selectOne("SELECT * FROM medications WHERE id = ?", [$medicationId]);
    if (!$med) Response::error('药品不存在');
    if ($med['stock_quantity'] < $quantity) {
        throw new Exception('库存不足（当前 ' . $med['stock_quantity'] . '）');
    }

    // 获取订单患者信息
    $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) throw new Exception('医嘱不存在');

    // 摆药扣费校验: 预缴金不足 (余额=预交+可透支-已用) 则摆药失败
    // (并发串行化: MySQL/PG 下对患者行 FOR UPDATE, 同患者并发摆药排队防余额快照穿透)
    if (DB_TYPE === 'mysql' || DB_TYPE === 'postgresql') {
        DB::execute("SELECT id FROM patients WHERE id = ? FOR UPDATE", [(int)$order['patient_id']]);
    }
    $funds = patient_funds((int)$order['patient_id']);
    if ($funds['balance'] < 0) {
        throw new Exception('患者预缴金不足，摆药失败（可用余额 ¥' . number_format($funds['balance'], 2) . '，请先补交费用）');
    }

    // 扣减库存 (WHERE 带余量条件: 并发下另一请求先扣光时 rowCount=0 拒绝, 防 TOCTOU 超卖;
    // 与 dual_pharmacy_helper 的原子扣减写法对齐)
    $aff = DB::execute("UPDATE medications SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?",
        [$quantity, $medicationId, $quantity]);
    if ($aff !== 1) throw new Exception('库存不足或并发冲突，请重试');

    // 记录发药
    $stmt = $pdo->prepare("INSERT INTO dispensing_records (patient_id, admission_no, order_id, medication_id, quantity, dispensed_by, dispensed_at, status, created_at) VALUES (?,?,?,?,?,?,?, 'dispensed', ?)");
    $stmt->execute([
        $order['patient_id'], $order['admission_no'], $orderId, $medicationId,
        $quantity, Auth::user()['id'], DateHelper::now(), DateHelper::now()
    ]);

    $pdo->commit();
    Response::success(['id' => $pdo->lastInsertId(), 'new_stock' => $med['stock_quantity'] - $quantity]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage(), 400);
}
