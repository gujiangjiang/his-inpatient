<?php
// api/modules/lab/lab_result.php
Auth::requireRole(['admin', 'lab_tech']);
$data = Validator::all();

$reportId = Validator::get('id');
$status = Validator::get('status', 'reported');

if (!$reportId) Response::error('请输入检验单ID');

$pdo = DB::getPDO();
$report = DB::selectOne("SELECT * FROM lab_reports WHERE id = ?", [$reportId]);
if (!$report) Response::error('检验单不存在');

// 状态机校验
if ($report['status'] !== 'collected' && $report['status'] !== 'pending') {
    Response::error('未采集不能录结果');
}

$pdo->beginTransaction();
try {
    DB::execute("UPDATE lab_reports SET status = ?, is_critical = ?, reported_by = ?, reported_at = ?, updated_at = ? WHERE id = ?",
        [$status, $data['is_critical'] ?? 0, Auth::user()['id'], DateHelper::now(), DateHelper::now(), $reportId]);

    // 更新明细 (WHERE 绑定 report_id: 防按 item.id 越权改写其他检验单的结果)
    $items = $data['items'] ?? [];
    foreach ($items as $item) {
        if (isset($item['id']) && $item['id']) {
            DB::execute("UPDATE lab_report_items SET result = ?, unit = ?, reference_range = ?, flag = ? WHERE id = ? AND report_id = ?",
                [$item['result'] ?? null, $item['unit'] ?? '', $item['reference_range'] ?? '', $item['flag'] ?? null, $item['id'], $reportId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO lab_report_items (report_id, item_name, result, unit, reference_range, flag) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$reportId, $item['item_name'], $item['result'] ?? null, $item['unit'] ?? '', $item['reference_range'] ?? '', $item['flag'] ?? null]);
        }
    }
    if ($status === 'reported') {
        $snapshot = print_snapshot_lab_payload($reportId);
        print_snapshot_capture('lab_report', $reportId, $snapshot, $report['patient_id'], $report['admission_no'], Auth::user()['id']);
    }

    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error($e->getMessage());
}
