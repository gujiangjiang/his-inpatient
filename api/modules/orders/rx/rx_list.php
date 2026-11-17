<?php
// api/modules/orders/rx_list.php - 患者处方列表 (按处方号归集, 含已作废)
Auth::requireRole(['admin', 'doctor']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);

$rows = DB::select(
    "SELECT o.id, o.rx_no, o.content, o.dosage, o.dosage_unit, o.usage_name, o.usage_rate,
            o.freq_code, o.freq_detail, o.status, o.order_period, o.start_date, o.created_at,
            o.notes, u.name AS creator_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.created_by
     WHERE o.patient_id = ? AND o.notes = '处方'
     ORDER BY o.id DESC",
    [$patientId]
);
Response::success($rows);
