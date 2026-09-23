<?php
// tools/lint/php-lint.php - PHP 语法检查工具
// 使用 PHP tokenizer (token_get_all + TOKEN_PARSE) 检测语法错误
// 无需系统 PHP, 可运行于: frankenphp php-cli tools/lint/php-lint.php

$basePath = dirname(__DIR__, 2);

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        if (strpos($path, '/vendor/') !== false) continue;
        if (strpos($path, '/node_modules/') !== false) continue;
        if (strpos($path, '/data/') !== false) continue;
        if (strpos($path, '/public/lib/') !== false) continue;
        $phpFiles[] = $path;
    }
}
sort($phpFiles);

$errors = [];
$checked = 0;
$total = count($phpFiles);

foreach ($phpFiles as $file) {
    $checked++;
    $code = file_get_contents($file);
    if ($code === false) {
        $errors[] = "  - $file: 无法读取文件";
        continue;
    }
    try {
        token_get_all($code, TOKEN_PARSE);
    } catch (ParseError $e) {
        $errors[] = "  - $file: {$e->getMessage()} (line {$e->getLine()})";
    }
    if ($checked % 10 === 0) {
        echo "已检查 $checked/$total 个文件...\n";
    }
}

echo "\n检查完成: $total 个 PHP 文件\n";

if (empty($errors)) {
    echo "全部通过! 没有语法错误。\n";
    exit(0);
}

echo "发现 " . count($errors) . " 个语法错误:\n";
foreach ($errors as $err) {
    echo $err . "\n";
}
exit(1);