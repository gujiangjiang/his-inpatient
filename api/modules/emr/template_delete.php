<?php
// api/modules/emr/template_delete.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$id = Validator::get('id');
if (!$id) Response::error('请输入模板ID');
DB::delete('emr_templates', 'id = ?', [$id]);
Response::success();
