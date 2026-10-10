<?php
// api/modules/orders/order_list.php - 医嘱列表 (树形: 根医嘱 + 子医嘱, 支持过滤)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$data = Validator::all();
$patientId = Validator::get('patient_id');
$status = Validator::get('status');            // 逗号分隔: draft,pending,...
$orderPeriod = Validator::get('order_period'); // long|temp
$dateFrom = Validator::get('date_from');
$dateTo = Validator::get('date_to');
$page = (int)Validator::get('page', 1);
$perPage = (int)Validator::get('per_page', 200);
if ($perPage < 1 || $perPage > 500) $perPage = 200;

$sql = "SELECT o.*, u.name AS creator_name
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        WHERE o.parent_id IS NULL";
$params = [];

if ($patientId) {
    $sql .= " AND o.patient_id = ?";
    $params[] = $patientId;
}

if ($status) {
    $statuses = array_filter(array_map('trim', explode(',', $status)));
    if ($statuses) {
        $sql .= " AND o.status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")";
        $params = array_merge($params, $statuses);
    }
}

if (in_array($orderPeriod, ['long', 'temp'])) {
    $sql .= " AND o.order_period = ?";
    $params[] = $orderPeriod;
}

if ($dateFrom) {
    $sql .= " AND o.start_date >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $sql .= " AND o.start_date <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

// 按角色过滤
$user = Auth::user();
if ($user['role'] === 'nurse') {
    $sql .= " AND o.patient_id IN (SELECT id FROM patients WHERE ward_id = ?)";
    $params[] = $user['ward_id'];
} elseif ($user['role'] === 'doctor') {
    $allowed = allowed_department_ids($user);
    if ($allowed) {
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $sql .= " AND o.patient_id IN (SELECT id FROM patients WHERE department_id IN ({$placeholders}))";
        $params = array_merge($params, $allowed);
    } else {
        $sql .= " AND 1 = 0";
    }
}

// 固定按开单顺序 (id 升序) 展示; 修改开始时间不影响顺序 (护士执行端可另按时间排序)
$sql .= " ORDER BY o.id ASC";

$offset = ($page - 1) * $perPage;
$sqlCount = "SELECT COUNT(*) AS total FROM (" . $sql . ") t";
$stmt = DB::getPDO()->prepare($sqlCount);
$stmt->execute($params);
$totalCount = $stmt->fetch()['total'];

$sql .= " LIMIT {$perPage} OFFSET {$offset}";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 子医嘱内嵌 (含开嘱医生名)
$result = [];
foreach ($orders as $order) {
    $subs = DB::select(
        "SELECT o.*, u.name AS creator_name FROM orders o
         LEFT JOIN users u ON o.created_by = u.id
         WHERE o.parent_id = ? ORDER BY o.group_seq ASC, o.id ASC",
        [$order['id']]
    );
    $order['sub_orders'] = $subs;
    $result[] = $order;
}

Response::paginated($result, $totalCount, $page, $perPage);
