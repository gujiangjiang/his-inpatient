<?php
// api/modules/exam/exam_detail.php
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$id = Validator::get('id');
if (!$id) Response::error('请输入检查单ID');
$report = DB::selectOne("SELECT er.*, p.name as patient_name, p.bed_no, u.name as creator_name
    FROM exam_reports er
    LEFT JOIN patients p ON er.patient_id = p.id
    LEFT JOIN users u ON er.created_by = u.id
    WHERE er.id = ?", [$id]);
if (!$report) Response::error('检查单不存在', 404);
Response::success($report);
