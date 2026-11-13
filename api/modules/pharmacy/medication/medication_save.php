<?php
// api/modules/pharmacy/medication_save.php
Auth::requireRole(['admin', 'pharmacist']);
$data = Validator::all();
$code = Validator::get('code');
$name = Validator::get('name');

if (!$code || !$name) Response::error('请输入药品编码和名称');

$pdo = DB::getPDO();
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE medications SET code=?, name=?, specification=?, unit=?, price=?, stock_quantity=? WHERE id=?");
    $stmt->execute([
        $code, $name, $data['specification'] ?? '',
        $data['unit'] ?? '', $data['price'] ?? 0,
        $data['stock_quantity'] ?? 0, $id
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO medications (code, name, specification, unit, price, stock_quantity, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $code, $name, $data['specification'] ?? '',
        $data['unit'] ?? '', $data['price'] ?? 0,
        $data['stock_quantity'] ?? 0,
        DateHelper::now()
    ]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
