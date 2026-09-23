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
require_once __DIR__ . '/core/migration.php';
require_once __DIR__ . '/routes.php';

Auth::init();

// 初始化守卫
SetupGuard::guard();

// 路由分发
$apiUri = preg_replace('#^/api/#', '', $_SERVER['REQUEST_URI']);
$apiUri = preg_replace('#\?.*$#', '', $apiUri);
$path = trim($apiUri, '/');

$routes = getRoutes();
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
