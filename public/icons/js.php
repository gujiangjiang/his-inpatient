<?php
// public/icons/js.php - SVG 图标 JS 桥接 (SVG 仍由 PHP 绘制; JS 动态渲染时通过 svgIcon(name,size) 取用)
// 引入: <script src="/icons/js.php"></script>
header('Content-Type: application/javascript; charset=utf-8');
require_once __DIR__ . '/icon.php';

$icons = [];
foreach (icon_all() as $key => $def) {
    $pos = strrpos($key, '/');
    $set = substr($key, 0, $pos);
    $name = substr($key, $pos + 1);
    $icons[$key] = icon($set, $name, '__SIZE__', '__CLASS__');
}

echo 'window.SvgIcons = ' . json_encode($icons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
echo "window.svgIcon = function(name, size, cls) {\n";
echo "    var tpl = window.SvgIcons[name];\n";
echo "    if (!tpl) return '';\n";
echo "    return tpl.replace(/__SIZE__/g, size || 24).replace('__CLASS__', cls || '');\n";
echo "};\n";
