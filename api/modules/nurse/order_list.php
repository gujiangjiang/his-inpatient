<?php
// api/modules/nurse/order_list.php - 护士站/核查清单医嘱 (待核查/已核对/执行中 + 发药标记 + 作废处方留痕)
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = Validator::get('patient_id');

// 待核查 (pending) 与已核对/执行 一并返回: 护士需要先看到待核查医嘱再核对
// 待作废 (invalid_pending) / 待停止 (stop_pending): 医生申请, 待护士确认
// 已作废处方医嘱保留展示 (毁方痕迹)
$sql = "SELECT o.*, u.name as doctor_name, p.name as patient_name, p.bed_no as bed_no, d.name as department_name,
        (SELECT MAX(dr.id) FROM dispensing_records dr WHERE dr.order_id = o.id AND dr.status = 'dispensed') AS dispensed_id
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        LEFT JOIN patients p ON o.patient_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE (o.status IN ('pending', 'invalid_pending', 'stop_pending', 'verified', 'executing')
               OR (o.status = 'invalid' AND o.notes = '处方'))";
$params = [];

if ($patientId) {
    $sql .= " AND o.patient_id = ?";
    $params[] = $patientId;
} elseif ($user['role'] === 'nurse') {
    // 护士只能看本病区患者的医嘱
    $sql .= " AND o.patient_id IN (SELECT id FROM patients WHERE ward_id = ?) ";
    $params[] = $user['ward_id'];
}

$sql .= " ORDER BY CASE o.status
            WHEN 'pending' THEN 0 WHEN 'invalid_pending' THEN 1 WHEN 'stop_pending' THEN 2
            WHEN 'verified' THEN 3 WHEN 'executing' THEN 4 ELSE 5 END, o.id ASC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
