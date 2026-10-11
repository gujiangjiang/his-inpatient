<?php
// api/modules/case_front_page/get.php - 病案首页读取 (结构化 data + 旧版富文本)
Auth::requireRole(['admin', 'doctor']);
$admissionNo = Validator::get('admission_no');
if (!$admissionNo) Response::error('请输入住院号');

$record = DB::selectOne("SELECT * FROM case_front_page WHERE admission_no = ? ORDER BY id DESC LIMIT 1", [$admissionNo]);
if ($record) {
    $record['data'] = json_decode((string)($record['data_json'] ?? ''), true) ?: null;
    unset($record['data_json']);
}
Response::success($record);
