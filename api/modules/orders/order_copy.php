<?php
// api/modules/orders/order_copy.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) Response::error('请输入医嘱ID');

$pdo = DB::getPDO();
$order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) Response::error('医嘱不存在');

// 复制条件: 仅限手动开具医嘱 (is_auto_generated = 0), 不能复制草稿
if ($order['is_auto_generated'] == 1) {
    Response::error('自动生成的医嘱不能复制');
}
if ($order['status'] === 'draft') {
    Response::error('草稿医嘱不能复制');
}

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $userId = Auth::user()['id'];

    // 检查药品可用性
    if ($order['category'] === 'medication') {
        // 查找关联的药品 (从 content 中提取药品名)
        $content = $order['content'];
        $medMatch = null;
        $meds = DB::select("SELECT * FROM medications");
        foreach ($meds as $med) {
            if (stripos($content, $med['name']) !== false) {
                $medMatch = $med;
                break;
            }
        }
        if (!$medMatch) {
            // 如果找不到药品，提示不明确
            Response::error('药品 "' . $content . '" 无法确定可用性，无法复制');
        }
        if ($medMatch['stock_quantity'] <= 0) {
            Response::error('药品 "' . $medMatch['name'] . '" 库存不足，无法复制');
        }
    } else {
        // 非药品: 检查项目是否存在 (暂时假设存在)
        // 在实际应用中, 可以检查 against 检验/检查项目库
        // 这里简单判断: 内容非空即可复制
        if (empty($order['content'])) {
            Response::error('医嘱内容为空，无法复制');
        }
    }

    // 创建新草稿医嘱
    $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, order_type, content, category, status, dosage, frequency, duration, created_by, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $order['patient_id'], $order['admission_no'],
        $order['order_type'], $order['content'], $order['category'],
        'draft', $order['dosage'], $order['frequency'], $order['duration'],
        $userId, '复制自 #' . $id, DateHelper::now(), $now
    ]);

    $newId = $pdo->lastInsertId();
    $pdo->commit();

    Response::success(['id' => $newId, 'copied' => true]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('复制失败：' . $e->getMessage());
}
