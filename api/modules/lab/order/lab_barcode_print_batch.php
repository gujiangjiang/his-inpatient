<?php
// api/modules/lab/order/lab_barcode_print_batch.php - 检验试管条码批量打印/补打留痕
Auth::requireRole(['admin', 'nurse', 'lab_tech']);
$ids = Validator::get('ids');
if (!is_array($ids) || !$ids) Response::error('请选择要打印的检验单');

$pdo = DB::getPDO();
$user = Auth::user();
$now = DateHelper::now();
$ok = 0; $fail = [];
$pdo->beginTransaction();
try {
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $report = DB::selectOne("SELECT id, patient_id FROM lab_reports WHERE id = ?", [$id]);
        if (!$report) { $fail[] = '检验单#' . $id; continue; }
        if ($user['role'] === 'nurse' && !can_access_patient($user, (int)$report['patient_id'])) {
            $fail[] = '检验单#' . $id; continue;
        }
        DB::execute(
            "UPDATE lab_reports
             SET barcode_print_count = COALESCE(barcode_print_count, 0) + 1,
                 barcode_printed_at = ?, barcode_printed_by = ?
             WHERE id = ?",
            [$now, (int)$user['id'], $id]
        );
        $ok++;
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('检验条码留痕失败：' . $e->getMessage());
}
Response::success(['ok' => $ok, 'fail' => $fail],
    '检验条码打印已记录 ' . $ok . ' 条' . ($fail ? '，跳过无效 ' . count($fail) . ' 条' : ''));