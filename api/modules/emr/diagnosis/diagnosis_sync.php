<?php
// api/modules/emr/diagnosis_sync.php - 诊断同步 (mode=admission: 门急诊→入院; mode=discharge: 入院→出院)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$patientId = (int)Validator::get('patient_id', 0);
$mode = (string)Validator::get('mode', '');
if (!$patientId || !in_array($mode, ['admission', 'discharge'], true)) Response::error('参数不完整');

$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);

$from = $mode === 'admission' ? 'emergency' : 'admission';
$to = $mode === 'admission' ? 'admission' : 'discharge';
$sourceRows = DB::select("SELECT * FROM patient_diagnoses WHERE patient_id = ? AND category = ? ORDER BY id", [$patientId, $from]);
if (!$sourceRows) Response::error('源分类暂无诊断可同步');

$targetRows = DB::select("SELECT name, IFNULL(icd_code, '') AS icd_code FROM patient_diagnoses WHERE patient_id = ? AND category = ?", [$patientId, $to]);
$exists = [];
foreach ($targetRows as $t) $exists[$t['name'] . '|' . $t['icd_code']] = true;

$copied = 0;
foreach ($sourceRows as $s) {
    $key = $s['name'] . '|' . (string)$s['icd_code'];
    if (isset($exists[$key])) continue;
    DB::insert('patient_diagnoses', [
        'patient_id' => $patientId,
        'admission_no' => $patient['admission_no'],
        'category' => $to,
        'icd_code' => (string)$s['icd_code'],
        'name' => (string)$s['name'],
        'note' => (string)($s['note'] ?? ''),
        'sort_no' => 0,
        'created_by' => $user['id'],
        'created_by_name' => $user['name'],
        'created_at' => DateHelper::now(),
    ]);
    $exists[$key] = true;
    $copied++;
}
Response::success(['copied' => $copied], $copied ? ('已同步 ' . $copied . ' 条诊断') : '目标分类已包含全部诊断，无需同步');
