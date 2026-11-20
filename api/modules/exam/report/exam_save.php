<?php
// api/modules/exam/exam_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$pdo = DB::getPDO();

/* 归属与一致性校验: admission_no 由服务端按患者反查, 不信任请求体 (防 A 患者挂 B 住院号) */
$patientId = (int)($data['patient_id'] ?? 0);
if (!$patientId) Response::error('请选择患者');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权为该患者开检查申请', 403);
$patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);

$reportNo = seq_serial('exam_reports', 'report_no', 'EXAM');
$examType = Validator::get('exam_type', '检查申请');

$stmt = $pdo->prepare("INSERT INTO exam_reports (patient_id, admission_no, report_no, exam_type, body_part, status, created_by, created_at) VALUES (?,?,?,?, 'pending', ?, ?)");
$stmt->execute([
    $patientId, $patient['admission_no'], $reportNo, $examType,
    $data['body_part'] ?? '', Auth::user()['id'], DateHelper::now()
]);

Response::success(['id' => $pdo->lastInsertId(), 'report_no' => $reportNo]);
