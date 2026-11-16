<?php
// api/modules/surgery/list.php - 手术申请列表 (按患者; 可选 include=cancelled)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);
$includeCancelled = Validator::get('include') === 'cancelled';
$sql = "SELECT s.*, su.name AS surgeon_name, a1.name AS assist1_name, a2.name AS assist2_name,
        o.status AS order_status, o.content AS order_content
        FROM surgeries s
        LEFT JOIN users su ON su.id = s.surgeon
        LEFT JOIN users a1 ON a1.id = s.assist1_id
        LEFT JOIN users a2 ON a2.id = s.assist2_id
        LEFT JOIN orders o ON o.id = s.order_id
        WHERE s.patient_id = ?" . ($includeCancelled ? '' : " AND IFNULL(s.status,'requested') <> 'cancelled'")
        . " ORDER BY s.id DESC LIMIT 100";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute([$patientId]);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
