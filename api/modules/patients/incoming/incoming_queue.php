<?php
// api/modules/patients/incoming_queue.php
// 待入科队列: 已办理住院登记 + 护士站已分配床位但尚未指派主管医师的患者
// 初期以内置 Mock 队列回退 (未对接护士站接口)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();

// 医生严格按授权科室范围过滤 (无授权则空集)
$sql = "SELECT p.*, d.name as department_name, w.name as ward_name,
        pa.attending_doctor_id, pa.senior_doctor_id
        FROM patients p
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN patient_assignments pa ON pa.patient_id = p.id
        WHERE p.status = 'active' AND p.bed_no IS NOT NULL AND pa.attending_doctor_id IS NULL";
$params = [];

// 医生按授权科室范围过滤 (无授权则空集); 该入口仅 admin/doctor, helper 的护士分支不触发
list($scopeSql, $scopeParams) = scope_where($user, 'p.ward_id', 'p.department_id');
if ($scopeSql !== '') { $sql .= $scopeSql; $params = array_merge($params, $scopeParams); }

$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
$incoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

Response::success(['source' => 'live', 'data' => $incoming]);