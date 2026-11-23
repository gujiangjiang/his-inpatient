<?php
// api/modules/exam/exam_report.php
Auth::requireRole(['admin', 'lab_tech']);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) Response::error('请输入检查单ID');

$pdo = DB::getPDO();
$report = DB::selectOne("SELECT * FROM exam_reports WHERE id = ?", [$id]);
if (!$report) Response::error('检查单不存在');
if ($report['status'] === 'reported') Response::error('检查报告已发布并形成法律快照，不能重复修改');

$pdo->beginTransaction();
try {
    DB::execute("UPDATE exam_reports SET status = 'reported', findings = ?, conclusion = ?, impression = ?, reported_by = ?, reported_at = ?, updated_at = ? WHERE id = ?",
        [$data['findings'] ?? '', $data['conclusion'] ?? '', $data['impression'] ?? '',
         Auth::user()['id'], DateHelper::now(), DateHelper::now(), $id]);
    $snapshot = print_snapshot_exam_payload($id);
    print_snapshot_capture('exam_report', $id, $snapshot, $report['patient_id'], $report['admission_no'], Auth::user()['id']);
    $pdo->commit();
    Response::success();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('检查报告发布失败：' . $e->getMessage(), 500);
}
