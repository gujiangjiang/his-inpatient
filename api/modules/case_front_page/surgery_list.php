<?php
// api/modules/case_front_page/surgery_list.php
Auth::requireRole(['admin', 'doctor']);
$admissionNo = Validator::get('admission_no');
if (!$admissionNo) Response::error('请输入住院号');

$surgery = DB::select("SELECT s.*, u.name as surgeon_name FROM surgeries s LEFT JOIN users u ON s.surgeon = u.id WHERE s.admission_no = ? ORDER BY s.created_at ASC", [$admissionNo]);
Response::success($surgery);
