<?php
// api/modules/emr/diagnosis_save.php - 添加患者诊断 (五分类), 入院诊断同步患者主档
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$category = (string)Validator::get('category', '');
$icdCode = trim((string)Validator::get('icd_code', ''));
$name = trim((string)Validator::get('name', ''));
$note = trim((string)Validator::get('note', ''));

$allowed = ['emergency', 'admission', 'discharge', 'pathology', 'external', 'nosocomial', 'complication', 'death_cause'];
if (!$patientId || !in_array($category, $allowed, true) || $name === '') Response::error('参数不完整');

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);

$dup = DB::selectOne(
    "SELECT id FROM patient_diagnoses WHERE patient_id = ? AND category = ? AND name = ?",
    [$patientId, $category, $name]
);
if ($dup) Response::error('该分类下已存在相同诊断');

$id = (int)DB::insert('patient_diagnoses', [
    'patient_id' => $patientId,
    'admission_no' => $patient['admission_no'],
    'category' => $category,
    'icd_code' => $icdCode,
    'name' => $name,
    'note' => $note,
    'sort_no' => 0,
    'created_by' => $user['id'],
    'created_by_name' => $user['name'],
    'created_at' => DateHelper::now(),
]);

// 入院诊断: 患者主档无入院诊断时同步 (供患者卡片/病历头展示)
if ($category === 'admission' && trim((string)$patient['admission_diagnosis']) === '') {
    DB::execute("UPDATE patients SET admission_diagnosis = ?, admission_icd_code = ? WHERE id = ?", [$name, $icdCode, $patientId]);
}
Response::success(['id' => $id], '诊断已添加');
