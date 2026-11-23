<?php
// api/modules/nurse/docs/link.php - 电子病历自动关联数据源
// 返回患者最近一条护理记录的生命体征快照 (供电子病历 vital 槽位自动关联)
// 关联规则: 以当前文书内容之前最新记录为准 (前端按 record_time 过滤)
Auth::requireRole(['admin', 'nurse', 'doctor']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('请输入患者ID');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权访问该患者', 403);

// 全部护理记录 (按时间升序, 前端按需取"文书内容之前最新")
$rows = DB::select(
    "SELECT id, record_time, temperature, pulse, respiratory_rate, bp_systolic, bp_diastolic,
            oxygen_saturation, intake_ml, output_ml, height, weight, blood_glucose
     FROM nursing_notes
     WHERE patient_id = ?
     ORDER BY record_time ASC, id ASC", [$patientId]);

Response::success($rows);
