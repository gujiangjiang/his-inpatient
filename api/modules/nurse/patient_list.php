<?php
// api/modules/nurse/patient_list.php
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$pdo = DB::getPDO();

$sql = "SELECT p.*, d.name as department_name, w.name as ward_name 
        FROM patients p 
        LEFT JOIN departments d ON p.department_id = d.id 
        LEFT JOIN wards w ON p.ward_id = w.id 
        WHERE p.status = 'active'";
$params = [];

// 护士按病区过滤
if ($user['role'] === 'nurse') {
    $sql .= " AND p.ward_id = ?";
    $params[] = $user['ward_id'];
}
$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 真实待办徽章: 待核查医嘱数 / 未发药 (已核对未发药的药品医嘱数)
if ($rows) {
    $ids = [];
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pending = [];
    foreach (DB::select("SELECT patient_id, COUNT(*) c FROM orders WHERE patient_id IN ($ph) AND status IN ('pending','invalid_pending','stop_pending') GROUP BY patient_id", $ids) as $r) {
        $pending[(int)$r['patient_id']] = (int)$r['c'];
    }
    $undispensed = [];
    $sqlU = "SELECT o.patient_id, COUNT(*) c FROM orders o
             WHERE o.patient_id IN ($ph) AND o.category = 'medication' AND o.status IN ('verified', 'executing')
               AND NOT EXISTS (SELECT 1 FROM dispensing_records dr WHERE dr.order_id = o.id AND dr.status = 'dispensed')
             GROUP BY o.patient_id";
    foreach (DB::select($sqlU, $ids) as $r) {
        $undispensed[(int)$r['patient_id']] = (int)$r['c'];
    }
    foreach ($rows as &$r) {
        $pid = (int)$r['id'];
        $r['pending_cnt'] = $pending[$pid] ?? 0;
        $r['undispensed_cnt'] = $undispensed[$pid] ?? 0;
    }
    unset($r);
}

Response::success($rows);
