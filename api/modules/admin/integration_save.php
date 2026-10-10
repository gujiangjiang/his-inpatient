<?php
// api/modules/admin/integration_save.php - 新增/编辑对接端点
Auth::requireRole(['admin']);
$data = Validator::all();
$id = Validator::get('id');
$name = trim((string)Validator::get('name', ''));
$systemType = Validator::get('system_type', 'FHIR');
$bizType = Validator::get('biz_type', '');
$baseUrl = trim((string)Validator::get('base_url', ''));
$authType = Validator::get('auth_type', 'none');
$authConfig = Validator::get('auth_config', '');
$enabled = (int)Validator::get('enabled', 1);
$remark = Validator::get('remark', '');

if ($name === '') Response::error('请输入端点名称');
if (!in_array($systemType, ['FHIR', 'HL7', 'HIS', 'DICOM'])) Response::error('不支持的系统类型');
if (!in_array($authType, ['none', 'basic', 'token', 'oauth2'])) Response::error('不支持的鉴权类型');
if ($authConfig !== '' && json_decode($authConfig, true) === null) Response::error('鉴权配置须为合法 JSON');

$now = DateHelper::now();
if ($id) {
    DB::execute(
        "UPDATE integration_endpoints SET name=?, system_type=?, biz_type=?, base_url=?, auth_type=?, auth_config=?, enabled=?, remark=?, updated_at=? WHERE id=?",
        [$name, $systemType, $bizType, $baseUrl, $authType, $authConfig, $enabled, $remark, $now, $id]
    );
    Response::success(['id' => $id]);
} else {
    DB::execute(
        "INSERT INTO integration_endpoints (name, system_type, biz_type, base_url, auth_type, auth_config, enabled, remark, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
        [$name, $systemType, $bizType, $baseUrl, $authType, $authConfig, $enabled, $remark, $now, $now]
    );
    Response::success(['id' => DB::getPDO()->lastInsertId()]);
}
