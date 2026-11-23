<?php
// public/worker.php - FrankenPHP Worker 模式入口
// 在生产环境下用于高性能响应 (常驻进程)
// 启动: frankenphp --watch --root public/ --listen :8080

// 加载应用核心
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/core/db/db.php';
require_once __DIR__ . '/../api/core/auth/auth.php';
require_once __DIR__ . '/../api/core/http/response.php';
require_once __DIR__ . '/../api/core/http/validator.php';
require_once __DIR__ . '/../api/core/schema/setup_guard.php';
require_once __DIR__ . '/../api/core/helpers/time/time_helper.php';
require_once __DIR__ . '/../api/core/helpers/time/date_helper.php';
require_once __DIR__ . '/../api/core/helpers/string/string_helper.php';
require_once __DIR__ . '/../api/core/helpers/id/id_generator_helper.php';
require_once __DIR__ . '/../api/core/schema/migration.php';
require_once __DIR__ . '/../api/core/schema/schema_guard.php';

// 路由分发
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';

// API 请求
if (strpos($uri, '/api/') === 0) {
    // 移除路由前缀
    $_SERVER['REQUEST_URI'] = $uri;

    // API 必须输出纯净 JSON: 警告/异常不得混入响应体
    ini_set('display_errors', '0');
    set_exception_handler(function ($e) {
        error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '服务器内部错误', 'data' => null], JSON_UNESCAPED_UNICODE);
        exit;
    });

    /* CSRF 防线 (与 api/index.php 一致): 写方法必须携带 X-Requested-With 头 */
    $reqMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($reqMethod, ['POST', 'PUT', 'DELETE', 'PATCH'], true)
        && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        Response::error('非法请求来源', 403);
    }

    Auth::init();
    SetupGuard::guard();
    SchemaGuard::ensure();
    require_once __DIR__ . '/../api/core/emr/emr_templates_guard.php';
    EmrTemplatesGuard::ensure();
    
    $apiUri = preg_replace('#^/api/#', '', $uri);
    $apiUri = preg_replace('#\?.*$#', '', $apiUri);
    $path = trim($apiUri, '/');
    
    // 加载路由表 (与 index.php 共享)
    $routes = getRoutes();
    
    $handler = $routes[$path] ?? null;
    if (!$handler) {
        Response::error('接口不存在', 404);
    }
    $handlerFile = __DIR__ . '/../api/modules/' . $handler[0];
    $requiredRoles = $handler[1] ?? [];
    
    if (!empty($requiredRoles)) {
        Auth::requireRole($requiredRoles);
    }
    
    if (is_file($handlerFile)) {
        require_once $handlerFile;
    } else {
        Response::error('接口实现不存在', 500);
    }
    return;
}

// 静态文件请求
$filePath = __DIR__ . $uri;
if (is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    readfile($filePath);
    return;
}

// SPA fallback
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
