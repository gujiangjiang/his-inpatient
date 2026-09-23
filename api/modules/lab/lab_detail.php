<?php
// api/modules/lab/lab_detail.php
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$id = Validator::get('id');

if (!$id) Response::error('请输入检验单ID');

$report = DB::selectOne("SELECT lr.*, p.name as patient_name, p.bed_no, u.name as creator_name
    FROM lab_reports lr
    LEFT JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN users u ON lr.created_by = u.id
    WHERE lr.id = ?", [$id]);

if (!$report) Response::error('检验单不存在', 404);

$items = DB::select("SELECT * FROM lab_report_items WHERE report_id = ? ORDER BY id ASC", [$id]);
$report['items'] = $items;

Response::success($report);
