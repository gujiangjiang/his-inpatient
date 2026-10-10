<?php
// public/icons/icon.php - 全站 SVG 图标渲染器 (禁止 emoji, 图标统一由 PHP 绘制)
//
// 用法 (PHP 页面/片段):
//   require_once __DIR__ . '/../../icons/icon.php';
//   然后输出:  icon('common/arrows', 'caret-right', 12)
//             icon('medical/objects', 'hospital', 72, 'placeholder-svg')
//             icon('common/actions', 'close', 10, '', 'id="btn-close"')
//
// 图标集: public/icons/{分类}/{功能}.php
//   返回 [名称 => 路径串 或 ['paths' => .., 'stroke_width' => .., 'viewBox' => ..]]
//   分类目录可按功能继续细分, 如 common/arrows.php, common/actions.php, medical/objects.php
//
// JS 动态渲染: 引入 /icons/js.php 后使用 svgIcon('common/actions/more', 16)

/**
 * 加载图标集 (按目录路径, 含静态缓存)
 */
function icon_set($set) {
    static $cache = [];
    if (!array_key_exists($set, $cache)) {
        $file = __DIR__ . '/' . ltrim($set, '/') . '.php';
        $cache[$set] = is_file($file) ? require $file : [];
    }
    return $cache[$set];
}

/**
 * 渲染单个 SVG 图标
 * @param string $set    图标集, 如 common/arrows
 * @param string $name   图标名, 如 caret-right
 * @param int    $size   尺寸 (px)
 * @param string $class  附加 CSS 类
 * @param string $attrs  附加属性串 (如 id="x")
 */
function icon($set, $name, $size = 24, $class = '', $attrs = '') {
    $icons = icon_set($set);
    if (!isset($icons[$name])) return '';
    $def = $icons[$name];
    $inner = is_array($def) ? ($def['paths'] ?? '') : $def;
    $viewBox = is_array($def) ? ($def['viewBox'] ?? '0 0 24 24') : '0 0 24 24';
    $strokeWidth = is_array($def) ? ($def['stroke_width'] ?? 1.8) : 1.8;
    return sprintf(
        '<svg class="svg-icon%s" width="%s" height="%s" viewBox="%s" fill="none" stroke="currentColor" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"%s>%s</svg>',
        $class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES) : '',
        $size, $size, $viewBox, $strokeWidth,
        $attrs !== '' ? ' ' . $attrs : '',
        $inner
    );
}

/**
 * 全部图标 (键: 分类/名称), 供 JS 桥接输出
 */
function icon_all() {
    $sets = ['common/arrows', 'common/actions', 'common/status', 'medical/objects'];
    $all = [];
    foreach ($sets as $set) {
        foreach (icon_set($set) as $name => $def) {
            $all[$set . '/' . $name] = $def;
        }
    }
    return $all;
}
