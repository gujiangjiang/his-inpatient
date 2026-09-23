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

    // 扣减库存
    DB::execute("UPDATE medications SET stock_quantity = stock_quantity - ? WHERE id = ?", [$quantity, $medicationId]);

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
