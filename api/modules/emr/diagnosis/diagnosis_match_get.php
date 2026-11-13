<?php
// api/modules/emr/diagnosis_match_get.php - 患者诊断符合情况 (九项)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);
$rows = DB::select("SELECT mkey, mvalue FROM patient_diag_match WHERE patient_id = ?", [$patientId]);
$map = [];
foreach ($rows as $r) $map[$r['mkey']] = (string)$r['mvalue'];
Response::success($map);
