<?php
// api/modules/emr/diagnosis_delete.php - 删除患者诊断
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$id = (int)Validator::get('id', 0);
$row = DB::selectOne("SELECT * FROM patient_diagnoses WHERE id = ?", [$id]);
if (!$row) Response::error('诊断不存在', 404);
if (!can_access_patient($user, $row['patient_id'])) Response::error('无权操作该患者', 403);

DB::execute("DELETE FROM patient_diagnoses WHERE id = ?", [$id]);
// 若删除的是已同步到主档的入院诊断, 清空主档字段
$patient = DB::selectOne("SELECT admission_diagnosis FROM patients WHERE id = ?", [$row['patient_id']]);
if ($row['category'] === 'admission' && $patient && $patient['admission_diagnosis'] === $row['name']) {
    DB::execute("UPDATE patients SET admission_diagnosis = '', admission_icd_code = '' WHERE id = ?", [$row['patient_id']]);
}
Response::success(null, '已删除');
