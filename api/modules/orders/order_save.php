<?php
// api/modules/orders/order_save.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$patientId = Validator::get('patient_id');
$content = Validator::get('content');
$category = Validator::get('category');
$orderType = Validator::get('order_type', 'medication');

if (!$patientId || !$content) Response::error('请输入患者和医嘱内容');

$pdo = DB::getPDO();
$userId = Auth::user()['id'];

// 校验患者
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');

$pdo->beginTransaction();
try {
    // 保存主医嘱
    $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, order_type, content, category, status, start_date, end_date, created_by, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patientId, $patient['admission_no'],
        $orderType, $content, $category,
        'pending',
        $data['start_date'] ?? DateHelper::now(),
        $data['end_date'] ?? '',
        $userId, $data['notes'] ?? '',
        DateHelper::now(), DateHelper::now()
    ]);

    // 保存子医嘱 (动态行)
    $mainId = $pdo->lastInsertId();
    $subOrders = $data['sub_orders'] ?? [];
    if (is_array($subOrders) && count($subOrders) > 0) {
        $subStmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, content, category, status, start_date, end_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($subOrders as $sub) {
            $subStmt->execute([
                $patientId, $patient['admission_no'], $mainId, 0,
                $sub['type'] ?? $orderType,
                $sub['content'] ?? '', $sub['category'] ?? $category,
                'pending',
                $sub['start_date'] ?? DateHelper::now(),
                $sub['end_date'] ?? '',
                $userId, DateHelper::now(), DateHelper::now()
            ]);
        }
    }

    $pdo->commit();
    Response::success(['id' => $mainId]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('开嘱失败：' . $e->getMessage());
}
