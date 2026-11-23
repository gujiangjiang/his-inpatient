<?php
// api/modules/lab/order/barcode_print.php - 检验试管条码打印/补打留痕
Auth::requireRole(['admin', 'nurse', 'lab_tech']);
$id = (int)Validator::get('id', 0);
if (!$id) Response::error('请输入检验单ID');

$report = DB::selectOne("SELECT id, patient_id, report_no FROM lab_reports WHERE id = ?", [$id]);
if (!$report) Response::error('检验单不存在', 404);
$user = Auth::user();
if ($user['role'] === 'nurse' && !can_access_patient($user, (int)$report['patient_id'])) {
    Response::error('无权打印该患者检验条码', 403);
}

$now = DateHelper::now();
DB::execute(
    "UPDATE lab_reports
     SET barcode_print_count = COALESCE(barcode_print_count, 0) + 1,
         barcode_printed_at = ?, barcode_printed_by = ?
     WHERE id = ?",
    [$now, (int)$user['id'], $id]
);
$printed = DB::selectOne(
    "SELECT lr.barcode_print_count, lr.barcode_printed_at, u.name AS barcode_printed_name
     FROM lab_reports lr
     LEFT JOIN users u ON u.id = lr.barcode_printed_by
     WHERE lr.id = ?", [$id]
);
Response::success([
    'id' => $id,
    'report_no' => $report['report_no'],
    'barcode_print_count' => (int)($printed['barcode_print_count'] ?? 0),
    'barcode_printed_at' => $printed['barcode_printed_at'] ?? '',
    'barcode_printed_name' => $printed['barcode_printed_name'] ?? '',
], ((int)($printed['barcode_print_count'] ?? 0) > 1) ? '检验条码补打已记录' : '检验条码打印已记录');
