<?php
// api/modules/orders/set_save.php - 另存为医嘱组套
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$name = trim((string)Validator::get('name', ''));
$category = Validator::get('category', 'medication');
$items = Validator::get('items');

if ($name === '') Response::error('请输入组套名称');
if (!is_array($items) || count($items) === 0) Response::error('组套至少包含一条医嘱');

$user = Auth::user();
$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO order_sets (name, category, department_id, is_active, created_by, created_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$name, $category, $user['department_id'] ?? null, 1, $user['id'], DateHelper::now()]);
    $setId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO order_set_items (set_id, category, drug_id, content, dosage, dosage_unit, usage_name, usage_rate, freq_code, freq_detail, order_period, group_seq, sort_no, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($items as $i => $item) {
        if (empty($item['content'])) continue;
        $itemStmt->execute([
            $setId, $item['category'] ?? $category, $item['drug_id'] ?? null,
            $item['content'], $item['dosage'] ?? '', $item['dosage_unit'] ?? '',
            $item['usage_name'] ?? '', $item['usage_rate'] ?? '',
            $item['freq_code'] ?? '', $item['freq_detail'] ?? '',
            $item['order_period'] ?? 'long', $i, $i, $item['notes'] ?? ''
        ]);
    }
    $pdo->commit();
    Response::success(['id' => $setId]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('保存组套失败：' . $e->getMessage());
}
