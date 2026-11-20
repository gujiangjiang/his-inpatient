<?php
// api/modules/emr/get.php
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$id = Validator::get('id');

if (!$id) {
    Response::error('请输入病历ID');
}

$record = DB::selectOne("SELECT * FROM emr_records WHERE id = ?", [$id]);
if (!$record) {
    Response::error('病历不存在', 404);
}
if (!can_access_patient(Auth::user(), (int)$record['patient_id'])) Response::error('无权访问该病历', 403);
Response::success($record);
