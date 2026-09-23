<?php
// api/modules/patients/incoming_queue.php
// 待入科队列: 已办理住院登记 + 护士站已分配床位但尚未指派主管医师的患者
// 初期以内置 Mock 队列回退 (未对接护士站接口)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();

// 医生有权限管辖的科室
$deptSql = "SELECT department_id FROM doctor_department_permissions WHERE doctor_id = ?";
$permittedDepts = array_column(DB::select($deptSql, [$user['id']]), 'department_id');

$sql = "SELECT p.*, d.name as department_name, w.name as ward_name,
        pa.attending_doctor_id, pa.senior_doctor_id
        FROM patients p
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN patient_assignments pa ON pa.patient_id = p.id
        WHERE p.status = 'active' AND p.bed_no IS NOT NULL AND pa.attending_doctor_id IS NULL";
$params = [];

if ($user['role'] === 'doctor' && !empty($permittedDepts)) {
    $placeholders = implode(',', array_fill(0, count($permittedDepts), '?'));
    $sql .= " AND p.department_id IN ({$placeholders})";
    $params = array_merge($params, $permittedDepts);
}

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
$incoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::success(['source' => 'live', 'data' => $incoming]);