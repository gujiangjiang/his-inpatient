<?php
// api/modules/case_front_page/get.php
Auth::requireRole(['admin', 'doctor']);
$admissionNo = Validator::get('admission_no');
if (!$admissionNo) Response::error('请输入住院号');

$record = DB::selectOne("SELECT * FROM case_front_page WHERE admission_no = ? ORDER BY id DESC LIMIT 1", [$admissionNo]);
Response::success($record);
