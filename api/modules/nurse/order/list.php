<?php
// api/modules/nurse/order/list.php - 护士站医嘱（患者详情全量留痕；核查清单仅返回可处理项）
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);

// 无 patient_id 时供「核查清单」使用，只返回当前可处理医嘱。
// 有 patient_id 时供床位患者详情使用，显示除草稿外的全部状态（包括所有作废来源）；
// 打印接口在该完整留痕基础上再排除作废，避免列表与纸质医嘱单语义混淆。
$sql = "SELECT o.*, u.name AS doctor_name, su.name AS stopped_name,
        p.name AS patient_name, p.bed_no AS bed_no, d.name AS department_name,
        (SELECT MAX(dr.id) FROM dispensing_records dr
         WHERE dr.status IN ('dispensed', 'delivered')
           AND (dr.order_id = o.id OR (o.parent_id IS NULL AND dr.order_id IN
                (SELECT co.id FROM orders co WHERE co.parent_id = o.id)))) AS dispensed_id
        FROM orders o
        LEFT JOIN users u ON o.created_by = u.id
        LEFT JOIN users su ON o.stopped_by = su.id
        LEFT JOIN patients p ON o.patient_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE ";
$params = [];

if ($patientId) {
    if (!can_access_patient($user, $patientId)) Response::error('无权访问该患者', 403);
    // 患者详情展示全部已提交医嘱（含所有来源的作废医嘱），唯一排除从未提交的草稿。
    $sql .= "o.status <> 'draft' AND o.patient_id = ?";
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
