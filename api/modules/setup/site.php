<?php
// api/modules/setup/site.php - 向导第 4 步：站点信息（医院名称/机构代码/全站时区）
require_once __DIR__ . '/_common.php';
setup_require_open();
setup_require_stage(array('site', 'admin'));   // 允许回退修改

$hospitalName = trim((string)Validator::get('hospital_name', ''));
$hospitalCode = trim((string)Validator::get('hospital_code', ''));
if ($hospitalName === '' || $hospitalCode === '') Response::error('请填写医院名称与组织机构代码');

$tz = setup_valid_tz(Validator::get('timezone', ''));
if ($tz === '') Response::error('请选择有效的时区');

// 业务配置写 system_config（站点信息 + 更新时间戳为存储口径）
DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
    array('hospital_name', $hospitalName, 'system', '医院名称', DateHelper::now()));
DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
    array('hospital_code', $hospitalCode, 'system', '组织机构代码', DateHelper::now()));

// 全站时区写 config.db（文化/展示口径；存储始终 UTC），当前请求即时生效
ConfigDB::setMany(array('timezone' => $tz, 'setup.stage' => 'admin'));
date_default_timezone_set($tz);

Response::success(array('timezone' => $tz, 'stage' => 'admin'), '站点信息已保存');
