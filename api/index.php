<?php
// api/index.php - 统一路由入口 (基础设施加载见 core/bootstrap.php)
require_once __DIR__ . '/core/bootstrap.php';

// API 必须输出纯净 JSON: 警告/异常不得混入响应体 (否则前端 JSON.parse 报 Unexpected token '<')
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => '服务器内部错误', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
});

/* CSRF 防线: 写方法必须携带前端 AjaxLoader 统一附加的 X-Requested-With 头。
 * 浏览器原生跨站请求 (表单提交/自动提交的 img 等) 无法携带自定义头, 以此区分站内站外;
 * GET 读操作与 SSE (EventSource 无法带自定义头) 不受限。 */
$reqMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($reqMethod, ['POST', 'PUT', 'DELETE', 'PATCH'], true)
    && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    Response::error('非法请求来源', 403);
}

Auth::init();

// 初始化守卫
SetupGuard::guard();

// 部署自愈: 自动补齐未应用的数据库迁移 (升级后首个请求即生效)
SchemaGuard::ensure();

// 部署自愈: 自动生成/更新内置病历模板 (免 CLI/npm; 定义变更后自动重新落库)
require_once __DIR__ . '/core/emr/emr_templates_guard.php';
EmrTemplatesGuard::ensure();

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
