<?php
// api/modules/nurse/order_list.php - 护士站/核查清单医嘱 (待核查/已核对/执行中 + 发药标记 + 作废处方留痕)
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);

// 无 patient_id 时供「核查清单」使用，只返回当前可处理医嘱。
// 有 patient_id 时供床位患者详情使用，必须与医嘱单打印采用同一有效状态口径，
// 避免已完成/已停止医嘱只出现在打印单、列表却不可见；已作废处方仍额外留痕展示。
$sql = "SELECT o.*, u.name as doctor_name, p.name as patient_name, p.bed_no as bed_no, d.name as department_name,
        (SELECT MAX(dr.id) FROM dispensing_records dr WHERE dr.order_id = o.id AND dr.status = 'dispensed') AS dispensed_id
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        LEFT JOIN patients p ON o.patient_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE ";
$params = [];

if ($patientId) {
    if (!can_access_patient($user, $patientId)) Response::error('无权访问该患者', 403);
    $sql .= "(o.status IN ('pending', 'invalid_pending', 'stop_pending', 'verified', 'executing', 'completed', 'stopped')
              OR (o.status = 'invalid' AND o.notes = '处方'))
             AND o.patient_id = ?";
    $params[] = $patientId;
} else {
    $sql .= "(o.status IN ('pending', 'invalid_pending', 'stop_pending', 'verified', 'executing')
              OR (o.status = 'invalid' AND o.notes = '处方'))";
}
if ($user['role'] === 'nurse') {
    // 护士只能看本病区患者的医嘱 (与 patient_id 条件叠加, 不可因传 patient_id 绕开病区过滤)
    $sql .= " AND o.patient_id IN (SELECT id FROM patients WHERE ward_id = ?) ";
    $params[] = $user['ward_id'];
}

$sql .= " ORDER BY CASE o.status
            WHEN 'pending' THEN 0 WHEN 'invalid_pending' THEN 1 WHEN 'stop_pending' THEN 2
            WHEN 'verified' THEN 3 WHEN 'executing' THEN 4 WHEN 'completed' THEN 5
            WHEN 'stopped' THEN 6 ELSE 7 END, o.id ASC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
