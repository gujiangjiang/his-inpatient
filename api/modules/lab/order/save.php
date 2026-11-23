<?php
// api/modules/lab/lab_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$pdo = DB::getPDO();

/* 归属与一致性校验: admission_no 由服务端按患者反查, 不信任请求体 (防 A 患者挂 B 住院号) */
$patientId = (int)($data['patient_id'] ?? 0);
if (!$patientId) Response::error('请选择患者');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权为该患者开检验申请', 403);
$patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);

$reportNo = seq_serial('lab_reports', 'report_no', 'LAB');
$examName = Validator::get('exam_name', '检验申请');

$stmt = $pdo->prepare("INSERT INTO lab_reports (patient_id, admission_no, report_no, exam_name, specimen_type, status, created_by, created_at) VALUES (?,?,?,?,?, 'pending', ?, ?)");
$stmt->execute([
    $patientId, $patient['admission_no'], $reportNo, $examName, $data['specimen_type'] ?? '',
    Auth::user()['id'], DateHelper::now()
]);
$id = $pdo->lastInsertId();

// 保存明细项
$items = $data['items'] ?? [];
if (is_array($items)) {
    $itemStmt = $pdo->prepare("INSERT INTO lab_report_items (report_id, item_name, result, unit, reference_range, flag) VALUES (?,?,?,?,?,?)");
    foreach ($items as $item) {
        $itemStmt->execute([
            $id,
            $item['item_name'] ?? '',
            $item['result'] ?? null,
            $item['unit'] ?? '',
            $item['reference_range'] ?? '',
            $item['flag'] ?? null
        ]);
    }
}

Response::success(['id' => $id, 'report_no' => $reportNo]);
