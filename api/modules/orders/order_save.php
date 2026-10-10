<?php
// api/modules/orders/order_save.php - 保存医嘱 (支持成组/结构化字段)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$patientId = Validator::get('patient_id');
$orderPeriod = Validator::get('order_period', 'long');
if (!in_array($orderPeriod, ['long', 'temp'])) $orderPeriod = 'long';

$category = Validator::get('category', 'general');
$submit = (int)Validator::get('submit', 0);

// 统一为 items: 兼容旧前端 content + sub_orders 结构
$items = Validator::get('items');
if (!is_array($items) || count($items) === 0) {
    $content = Validator::get('content');
    if (!$content) Response::error('请输入患者和医嘱内容');
    $items = [[
        'content' => $content,
        'category' => $category,
        'dosage' => Validator::get('dosage', ''),
        'frequency' => Validator::get('frequency', ''),
    ]];
    foreach ((array)Validator::get('sub_orders', []) as $sub) {
        $items[] = [
            'content' => $sub['content'] ?? '',
            'category' => $sub['category'] ?? $category,
            'dosage' => $sub['dosage'] ?? '',
        ];
    }
}

if (!$patientId) Response::error('请选择患者');
foreach ($items as $item) {
    if (empty($item['content'])) Response::error('医嘱内容不能为空');
}

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);

$groupId = Validator::get('group_id');   // 组级用法 (前端组容器)
$groupUsage = Validator::get('usage_name', '');
$groupRate = Validator::get('usage_rate', '');
$startDate = Validator::get('start_date', DateHelper::now());
$endDate = Validator::get('end_date', '');
$notes = Validator::get('notes', '');
$status = $submit ? 'pending' : 'draft';
$userId = Auth::user()['id'];
$now = DateHelper::now();

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    $sql = "INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period,
                order_no, content, category, status, drug_id, dosage, dosage_unit, usage_name, usage_rate,
                freq_code, freq_detail, billing_flags, group_seq, sort_no, start_date, end_date, notes,
                created_by, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);

    $isGroup = count($items) > 1;
    $mainId = null;
    $ids = [];
    foreach ($items as $i => $item) {
        $orderNo = 'O' . date('YmdHis') . str_pad((string)mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
        $stmt->execute([
            $patientId, $patient['admission_no'],
            $i === 0 ? null : $mainId,
            ($isGroup && $i === 0) ? 1 : 0,
            ($item['category'] ?? $category) === 'medication' ? 'medication' : 'general',
            $item['order_period'] ?? $orderPeriod,
            $orderNo, $item['content'], $item['category'] ?? $category, $status,
            $item['drug_id'] ?? null,
            $item['dosage'] ?? '', $item['dosage_unit'] ?? '',
            $item['usage_name'] ?? $groupUsage, $item['usage_rate'] ?? $groupRate,
            $item['freq_code'] ?? $item['frequency'] ?? '', $item['freq_detail'] ?? '',
            $item['billing_flags'] ?? '', $i, 0,
            $item['start_date'] ?? $startDate, $endDate, $notes,
            $userId, $now, $now
        ]);
        $id = $pdo->lastInsertId();
        $ids[] = $id;
        if ($i === 0) $mainId = $id;
    }

    $pdo->commit();
    Response::success(['id' => $mainId, 'ids' => $ids]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('开嘱失败：' . $e->getMessage());
}
