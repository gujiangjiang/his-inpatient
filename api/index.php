<?php
// api/index.php - 统一路由入口
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Cache.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/validator.php';
require_once __DIR__ . '/core/setup_guard.php';
require_once __DIR__ . '/core/helpers/time_helper.php';
require_once __DIR__ . '/core/helpers/date_helper.php';
require_once __DIR__ . '/core/helpers/array_helper.php';
require_once __DIR__ . '/core/helpers/string_helper.php';
require_once __DIR__ . '/core/helpers/upload_helper.php';
require_once __DIR__ . '/core/helpers/department_helper.php';
require_once __DIR__ . '/core/helpers/fee_helper.php';
require_once __DIR__ . '/core/helpers/patient_helper.php';
require_once __DIR__ . '/core/helpers/dept_helper.php';
require_once __DIR__ . '/core/helpers/emr_guard_helper.php';
require_once __DIR__ . '/core/helpers/emr_slot_value.php';
require_once __DIR__ . '/core/helpers/emr_calc_helper.php';
require_once __DIR__ . '/core/helpers/emr_diagnosis_helper.php';
require_once __DIR__ . '/core/dict_db.php';
require_once __DIR__ . '/core/emr_dicts.php';
require_once __DIR__ . '/core/helpers/notify_helper.php';
require_once __DIR__ . '/core/helpers/order_set_helper.php';
require_once __DIR__ . '/core/helpers/pub_helper.php';
require_once __DIR__ . '/core/helpers/net_diag_helper.php';
require_once __DIR__ . '/core/helpers/integration_helper.php';
require_once __DIR__ . '/core/helpers/dual_pharmacy_helper.php';
require_once __DIR__ . '/core/helpers/opd_pharmacy_helper.php';
require_once __DIR__ . '/core/helpers/audit_helper.php';
require_once __DIR__ . '/core/helpers/cache_driver_helper.php';
require_once __DIR__ . '/core/helpers/opd_helper.php';
require_once __DIR__ . '/core/helpers/opd_order_push.php';
require_once __DIR__ . '/core/helpers/catalog_sync.php';
require_once __DIR__ . '/core/migration.php';
require_once __DIR__ . '/core/schema_guard.php';
require_once __DIR__ . '/routes.php';

// API 必须输出纯净 JSON: 警告/异常不得混入响应体 (否则前端 JSON.parse 报 Unexpected token '<')
ini_set('display_errors', '0');
set_exception_handler(function ($e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $msg = '服务器内部错误';
    // 诊断模式：请求带 debug=1（GET query 或 POST 体）时透出真实错误，仅排查用
    $isDebug = isset($_GET['debug']) && $_GET['debug'] === '1';
    if (!$isDebug && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if (strpos((string)$raw, '"debug":"1"') !== false || strpos((string)$raw, '"debug":1') !== false) $isDebug = true;
    }
    if ($isDebug) $msg = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    echo json_encode(['status' => 'error', 'message' => $msg, 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
});

Auth::init();

// 初始化守卫
SetupGuard::guard();

// 部署自愈: 自动补齐未应用的数据库迁移 (升级后首个请求即生效)
SchemaGuard::ensure();

// 部署自愈: 自动生成/更新内置病历模板 (免 CLI/npm; 定义变更后自动重新落库)
require_once __DIR__ . '/core/emr_templates_guard.php';
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
