<?php
// api/modules/exam/exam_report.php
Auth::requireRole(['admin', 'lab_tech']);
$data = Validator::all();
$id = Validator::get('id');

if (!$id) Response::error('请输入检查单ID');

$pdo = DB::getPDO();
$report = DB::selectOne("SELECT * FROM exam_reports WHERE id = ?", [$id]);
if (!$report) Response::error('检查单不存在');

DB::execute("UPDATE exam_reports SET status = 'reported', findings = ?, conclusion = ?, impression = ?, reported_by = ?, reported_at = ?, updated_at = ? WHERE id = ?",
    [$data['findings'] ?? '', $data['conclusion'] ?? '', $data['impression'] ?? '',
     Auth::user()['id'], DateHelper::now(), DateHelper::now(), $id]);

Response::success();
