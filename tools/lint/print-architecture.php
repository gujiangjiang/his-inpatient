<?php
// tools/lint/print-architecture.php - 防止打印窗口与系统打印能力重新散落到业务页面

$root = dirname(__DIR__, 2);
$jsRoot = $root . '/public/js';
$runtime = realpath($jsRoot . '/core/print_preview.js');
$required = [
    $jsRoot . '/core/print_document.js',
    $jsRoot . '/core/print_preview.js',
    $jsRoot . '/core/print_preview_content.js',
];
$errors = [];

foreach ($required as $file) {
    if (!is_file($file)) $errors[] = '缺少统一打印模块：' . substr($file, strlen($root) + 1);
}

$rules = [
    '/\bwindow\s*\.\s*print\s*\(/' => '业务代码不得直接调用 window.print()',
    '/\bdocument\s*\.\s*write\s*\(/' => '业务代码不得直接调用 document.write()',
    '/\bPrintHelper\s*\.\s*(?:print|printHtml|printDocument)\s*\(/' => '旧 PrintHelper 打印入口已移除',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($jsRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['js', 'php'], true)) continue;
    $path = $file->getRealPath();
    if ($path === $runtime) continue;
    $code = file_get_contents($path);
    foreach ($rules as $pattern => $message) {
        if (preg_match($pattern, $code)) {
            $errors[] = substr($path, strlen($root) + 1) . '：' . $message;
        }
    }
}

if ($runtime && is_file($runtime)) {
    $code = file_get_contents($runtime);
    if (preg_match_all('/\bwindow\s*\.\s*print\s*\(/', $code) !== 1) {
        $errors[] = 'print_preview.js 必须且只能保留一个 window.print() 调用';
    }
    if (preg_match_all('/\bdocument\s*\.\s*write\s*\(/', $code) !== 1) {
        $errors[] = 'print_preview.js 必须且只能保留一个 document.write() 调用';
    }
}

$assets = file_get_contents($root . '/public/assets.php');
$order = [
    '/js/core/print_document.js',
    '/js/core/print_helper.js',
    '/js/core/print_preview.js',
    '/js/core/print_preview_content.js',
];
$last = -1;
foreach ($order as $asset) {
    $position = strpos($assets, "'" . $asset . "'");
    if ($position === false) $errors[] = 'assets.php 未注册：' . $asset;
    elseif ($position <= $last) $errors[] = '打印模块资源加载顺序错误：' . $asset;
    else $last = $position;
}

if ($errors) {
    echo "打印架构检查失败：\n";
    foreach ($errors as $error) echo '  - ' . $error . "\n";
    exit(1);
}

echo "打印架构检查通过：浏览器打印能力仅存在于 PrintPreview。\n";
