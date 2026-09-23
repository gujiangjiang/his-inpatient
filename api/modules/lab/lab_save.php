<?php
// api/modules/lab/lab_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$pdo = DB::getPDO();
$reportNo = 'LAB' . date('Ymd') . str_pad($pdo->query("SELECT COUNT(*)+1 FROM lab_reports")->fetchColumn(), 4, '0', STR_PAD_LEFT);
$examName = Validator::get('exam_name', '检验申请');

$stmt = $pdo->prepare("INSERT INTO lab_reports (patient_id, admission_no, report_no, exam_name, specimen_type, status, created_by, created_at) VALUES (?,?,?,?,?, 'pending', ?, ?)");
$stmt->execute([
    $data['patient_id'], $data['admission_no'], $reportNo, $examName, $data['specimen_type'] ?? '',
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
