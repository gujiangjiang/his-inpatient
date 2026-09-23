<?php
// api/modules/emr/save.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$data = Validator::all();

$recordType = Validator::get('record_type');
$patientId = Validator::get('patient_id');
$contentDelta = Validator::get('content_delta');
$contentHtml = Validator::get('content_html');

if (!$recordType || !$patientId || !$contentDelta) {
    Response::error('缺少必要参数');
}

$pdo = DB::getPDO();
$userId = Auth::user()['id'];

// 校验患者隶属
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) {
    Response::error('患者不存在');
}

$stmt = $pdo->prepare("INSERT INTO emr_records (patient_id, admission_no, record_type, content_delta, content_html, diagnosis, icd_code, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->execute([
    $patientId,
    $patient['admission_no'],
    $recordType,
    $contentDelta,
    $contentHtml,
    $data['diagnosis'] ?? '',
    $data['icd_code'] ?? '',
    $userId,
    DateHelper::now()
]);

Response::success(['id' => $pdo->lastInsertId()]);
