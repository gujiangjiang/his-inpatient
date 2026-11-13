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

// 条件统一构建（主查询与计数共用；计数不走 JOIN，避免大表逐行连接开销）
$where = "o.parent_id IS NULL";
$params = [];

if ($patientId) {
    $where .= " AND o.patient_id = ?";
    $params[] = $patientId;
}

if ($status) {
    $statuses = array_filter(array_map('trim', explode(',', $status)));
    if ($statuses) {
        $where .= " AND o.status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")";
        $params = array_merge($params, $statuses);
    }
}

if (in_array($orderPeriod, ['long', 'temp'])) {
    $where .= " AND o.order_period = ?";
    $params[] = $orderPeriod;
}

if ($dateFrom) {
    $where .= " AND o.start_date >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where .= " AND o.start_date <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

// 按角色过滤
$user = Auth::user();
if ($user['role'] === 'nurse') {
    $where .= " AND o.patient_id IN (SELECT id FROM patients WHERE ward_id = ?)";
    $params[] = $user['ward_id'];
} elseif ($user['role'] === 'doctor') {
    $allowed = allowed_department_ids($user);
    if ($allowed) {
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $where .= " AND o.patient_id IN (SELECT id FROM patients WHERE department_id IN ({$placeholders}))";
        $params = array_merge($params, $allowed);
    } else {
        $where .= " AND 1 = 0";
    }
}

$offset = ($page - 1) * $perPage;

// 计数（不含 JOIN；与主查询同一 WHERE，结果一致）
$stmt = DB::getPDO()->prepare("SELECT COUNT(*) AS total FROM orders o WHERE {$where}");
$stmt->execute($params);
$totalCount = $stmt->fetch()['total'];

// 主查询：固定按开单顺序 (id 升序) 展示; 修改开始时间不影响顺序 (护士执行端可另按时间排序)
$sql = "SELECT o.*, u.name AS creator_name
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        WHERE {$where}
        ORDER BY o.id ASC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 子医嘱批量内嵌 (含开嘱医生名)：单次 IN 查询替代逐单单查（N+1 → 1）；
// 组内顺序 group_seq,id 与逐单查询一致；parent_id 命中索引（迁移 036）
$subsByParent = [];
if (count($orders)) {
    $rootIds = array_map(function ($o) { return (int)$o['id']; }, $orders);
    $ph = implode(',', array_fill(0, count($rootIds), '?'));
    $subs = DB::select(
        "SELECT o.*, u.name AS creator_name FROM orders o
         LEFT JOIN users u ON o.created_by = u.id
         WHERE o.parent_id IN ({$ph}) ORDER BY o.group_seq ASC, o.id ASC",
        $rootIds
    );
    foreach ($subs as $sub) {
        $subsByParent[(int)$sub['parent_id']][] = $sub;
    }
}
$result = [];
foreach ($orders as $order) {
    $oid = (int)$order['id'];
    $order['sub_orders'] = isset($subsByParent[$oid]) ? $subsByParent[$oid] : [];
    $result[] = $order;
}

Response::paginated($result, $totalCount, $page, $perPage);
