<?php
// api/modules/orders/order_list.php
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$data = Validator::all();
$patientId = Validator::get('patient_id');
$page = (int)Validator::get('page', 1);
$perPage = (int)Validator::get('per_page', 50);

// 树形展示：按根医嘱分页，子医嘱内嵌返回
$sql = "SELECT * FROM orders WHERE parent_id IS NULL";
$params = [];

if ($patientId) {
    $sql .= " AND patient_id = ?";
    $params[] = $patientId;
}

// 按角色过滤
$user = Auth::user();
if ($user['role'] === 'nurse') {
    $sql .= " AND patient_id IN (SELECT id FROM patients WHERE ward_id = ?)";
    $params[] = $user['ward_id'];
} elseif ($user['role'] === 'doctor') {
    $sql .= " AND patient_id IN (SELECT id FROM patients WHERE department_id = ?)";
    $params[] = $user['department_id'];
}

// 排序
$sql .= " ORDER BY created_at DESC";

// 分页
$offset = ($page - 1) * $perPage;
$sqlCount = "SELECT COUNT(*) as total FROM (" . $sql . ")";
$sql .= " LIMIT {$perPage} OFFSET {$offset}";

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取子医嘱
$result = [];
foreach ($orders as $order) {
    $subs = DB::select("SELECT * FROM orders WHERE parent_id = ? ORDER BY id ASC", [$order['id']]);
    $order['sub_orders'] = $subs;
    $result[] = $order;
}

$total = DB::getPDO()->prepare($sqlCount);
$total->execute($params);
$totalCount = $total->fetch()['total'];

Response::paginated($result, $totalCount, $page, $perPage);
