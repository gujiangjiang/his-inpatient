<?php
// api/modules/emr/template_use.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$id = Validator::get('id');

if (!$id) {
    Response::error('请输入模板ID');
}

DB::execute("UPDATE emr_templates SET usage_count = usage_count + 1 WHERE id = ?", [$id]);
Response::success();
