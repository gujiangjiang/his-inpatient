<?php
// api/modules/emr/template_list.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
$recordType = Validator::get('record_type', 'admission');

$templates = DB::select("SELECT * FROM emr_templates WHERE record_type = ? ORDER BY usage_count DESC, created_at DESC", [$recordType]);
Response::success($templates);
