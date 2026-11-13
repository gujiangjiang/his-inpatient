<?php
// api/modules/patients/bed_list.php - 床位列表 (按病区; summary=1 返回病区占用汇总)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$pdo = DB::getPDO();

$wardId = Validator::get('ward_id');

if (Validator::get('summary')) {
    $rows = DB::select(
        "SELECT w.id AS ward_id, w.name AS ward_name, w.bed_count,
                COUNT(b.id) AS total,
                SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) AS occupied
         FROM wards w LEFT JOIN beds b ON b.ward_id = w.id
         WHERE w.is_active = 1
         GROUP BY w.id, w.name, w.bed_count ORDER BY w.id"
    );
    Response::success($rows);
}

$sql = "SELECT b.*, w.name as ward_name, p.name as patient_name, p.nursing_level, p.is_critical
        FROM beds b
        LEFT JOIN wards w ON b.ward_id = w.id
        LEFT JOIN patients p ON b.patient_id = p.id";
$params = [];
if ($wardId) {
    $sql .= " WHERE b.ward_id = ?";
    $params[] = $wardId;
}
$sql .= " ORDER BY w.id, b.bed_no";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
