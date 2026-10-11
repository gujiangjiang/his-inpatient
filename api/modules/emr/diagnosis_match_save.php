<?php
// api/modules/emr/diagnosis_match_save.php - 保存单项诊断符合情况
Auth::requireRole(['admin', 'doctor']);
$patientId = (int)Validator::get('patient_id', 0);
$mkey = (string)Validator::get('mkey', '');
$mvalue = (string)Validator::get('mvalue', '');

$allowedKeys = ['out_in', 'adm_out', 'pre_post', 'rad_post', 'clin_path', 'rad_path', 'clin_autopsy', 'tcm_out', 'tcm_adm_out'];
if (!$patientId || !in_array($mkey, $allowedKeys, true)) Response::error('参数不完整');
if (!in_array($mvalue, ['', '0', '1', '2', '3'], true)) Response::error('取值不合法');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);

$existing = DB::selectOne("SELECT id FROM patient_diag_match WHERE patient_id = ? AND mkey = ?", [$patientId, $mkey]);
if ($existing) {
    DB::execute("UPDATE patient_diag_match SET mvalue = ?, updated_at = ? WHERE id = ?", [$mvalue, DateHelper::now(), $existing['id']]);
} else {
    DB::insert('patient_diag_match', [
        'patient_id' => $patientId, 'mkey' => $mkey, 'mvalue' => $mvalue, 'updated_at' => DateHelper::now(),
    ]);
}
Response::success(null, '已保存');
