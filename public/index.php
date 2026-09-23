<?php
// public/index.php - FrankenPHP php-server 路由入口
// Caddy php_server 的 try_files fallback: 不存在的路径会回退到这里
// 因此: /api/* → 交由 API 处理; 其他 → SPA index.html

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API 路由
if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/../api/index.php';
    return;
}

// SPA fallback
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');