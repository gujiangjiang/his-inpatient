<?php
// api/modules/orders/rx_list.php - 患者处方列表 (按处方号归集, 含已作废)
Auth::requireRole(['admin', 'doctor']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);

$rows = DB::select(
    "SELECT id, rx_no, content, dosage, dosage_unit, usage_name, status, order_period,
            start_date, created_at, notes
     FROM orders
     WHERE patient_id = ? AND notes = '处方'
     ORDER BY id DESC",
    [$patientId]
);
Response::success($rows);
