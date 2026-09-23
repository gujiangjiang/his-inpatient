<?php
// 内置服务器路由：/api/** 走 api/index.php，其余显式返回 public/ 文件并设 MIME
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicDir = __DIR__ . '/public';

// 根路径 → index.html
if ($uri === '/' || $uri === '') {
    $uri = '/index.html';
}

// 非 API 路径：显式返回静态文件，设置正确 MIME
if (strpos($uri, '/api/') !== 0) {
    $file = $publicDir . $uri;
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html; charset=utf-8',
            'css'  => 'text/css',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'  => 'font/ttf',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($file);
        return;
    }
    // SPA fallback
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/index.html');
    return;
}

// API 路由
require __DIR__ . '/api/index.php';
