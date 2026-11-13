<?php
// api/modules/exam/exam_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$pdo = DB::getPDO();

$reportNo = 'EXAM' . date('Ymd') . str_pad(DB::selectOne("SELECT COUNT(*) as c FROM exam_reports")['c'] + 1, 4, '0', STR_PAD_LEFT);
$examType = Validator::get('exam_type', '检查申请');

$stmt = $pdo->prepare("INSERT INTO exam_reports (patient_id, admission_no, report_no, exam_type, body_part, status, created_by, created_at) VALUES (?,?,?,?, 'pending', ?, ?)");
$stmt->execute([
    $data['patient_id'], $data['admission_no'], $reportNo, $examType,
    $data['body_part'] ?? '', Auth::user()['id'], DateHelper::now()
]);

Response::success(['id' => $pdo->lastInsertId(), 'report_no' => $reportNo]);
