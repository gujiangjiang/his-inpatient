<?php
// api/index.php - 统一路由入口
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/validator.php';
require_once __DIR__ . '/core/setup_guard.php';
require_once __DIR__ . '/core/helpers/date_helper.php';
require_once __DIR__ . '/core/helpers/array_helper.php';
require_once __DIR__ . '/core/helpers/string_helper.php';
require_once __DIR__ . '/core/helpers/upload_helper.php';

Auth::init();

// 初始化守卫
SetupGuard::guard();

// 路由表
$apiUri = preg_replace('#^/api/#', '', $_SERVER['REQUEST_URI']);
$apiUri = preg_replace('#\?.*$#', '', $apiUri);
$path = trim($apiUri, '/');

$routes = [
    // 认证
    'auth/check' => ['auth/check.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
    'auth/login' => ['auth/login.php', []],
    'auth/logout' => ['auth/logout.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
    'auth/setup-status' => ['auth/setup_status.php', []],
    'auth/setup' => ['auth/setup.php', []],

    // 后台管理
    'admin/department-list' => ['admin/department_list.php', ['admin']],
    'admin/department-save' => ['admin/department_save.php', ['admin']],
    'admin/ward-list' => ['admin/ward_list.php', ['admin']],
    'admin/ward-save' => ['admin/ward_save.php', ['admin']],
    'admin/user-list' => ['admin/user_list.php', ['admin']],
    'admin/user-save' => ['admin/user_save.php', ['admin']],
    'admin/api-config-list' => ['admin/api_config_list.php', ['admin']],
    'admin/api-config-save' => ['admin/api_config_save.php', ['admin']],
    'admin/sys-config-list' => ['admin/sys_config_list.php', ['admin']],
    'admin/sys-config-save' => ['admin/sys_config_save.php', ['admin']],

    // 患者管理
    'patients/list' => ['patients/patient_list.php', ['admin', 'doctor', 'nurse']],
    'patients/get' => ['patients/patient_get.php', ['admin', 'doctor', 'nurse']],
    'patients/save' => ['patients/patient_save.php', ['admin', 'doctor']],
    'patients/discharge' => ['patients/patient_discharge.php', ['admin', 'doctor']],
    'patients/beds' => ['patients/bed_list.php', ['admin', 'doctor']],

    // 病历
    'emr/save' => ['emr/save.php', ['admin', 'doctor']],
    'emr/list' => ['emr/list.php', ['admin', 'doctor', 'nurse']],
    'emr/get' => ['emr/get.php', ['admin', 'doctor', 'nurse']],
    'emr/icd-search' => ['emr/icd_search.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
    'emr/template-list' => ['emr/template_list.php', ['admin', 'doctor']],
    'emr/template-get' => ['emr/template_get.php', ['admin', 'doctor']],
    'emr/template-save' => ['emr/template_save.php', ['admin', 'doctor']],
    'emr/template-use' => ['emr/template_use.php', ['admin', 'doctor']],
];

$handler = $routes[$path] ?? null;
if (!$handler) {
    Response::error('接口不存在', 404);
}
$handlerFile = __DIR__ . '/modules/' . $handler[0];
$requiredRoles = $handler[1] ?? [];

if (!empty($requiredRoles)) {
    Auth::requireRole($requiredRoles);
}

if (!is_file($handlerFile)) {
    Response::error('接口实现不存在', 500);
}

require_once $handlerFile;
