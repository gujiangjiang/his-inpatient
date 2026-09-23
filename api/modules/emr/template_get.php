<?php
// api/modules/emr/template_get.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$id = Validator::get('id');

if (!$id) {
    Response::error('请输入模板ID');
}

$template = DB::selectOne("SELECT * FROM emr_templates WHERE id = ?", [$id]);
if (!$template) {
    Response::error('模板不存在', 404);
}
Response::success($template);
