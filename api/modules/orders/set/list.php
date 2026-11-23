<?php
// api/modules/orders/set_list.php - 医嘱组套列表
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$category = Validator::get('category');

$sql = "SELECT s.*, u.name AS creator_name,
        (SELECT COUNT(*) FROM order_set_items i WHERE i.set_id = s.id) AS item_count
        FROM order_sets s
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.is_active = 1";
$params = [];

if ($category) {
    $sql .= " AND s.category = ?";
    $params[] = $category;
}
if ($user['role'] === 'doctor' && !empty($user['department_id'])) {
    $sql .= " AND (s.department_id IS NULL OR s.department_id = ?)";
    $params[] = $user['department_id'];
}
$sql .= " ORDER BY s.id DESC";

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
$sets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sets as &$set) {
    $items = DB::select("SELECT * FROM order_set_items WHERE set_id = ? ORDER BY sort_no ASC, id ASC", [$set['id']]);
    $set['items'] = order_set_items_with_avail($items); /* 下架/变更 → avail.ok=false */
    $set['invalid_count'] = count(array_filter($set['items'], function ($i) { return empty($i['avail']['ok']); }));
}
unset($set);

Response::success($sets);
