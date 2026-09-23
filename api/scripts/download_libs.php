<?php
// api/scripts/download_libs.php - 下载 Quill.js 和 html2pdf.js 到本地 (用于内网部署)
require_once __DIR__ . '/../config.php';

$libDir = BASE_DIR . '/public/lib';

$urls = [
    'quill/quill.min.js' => 'https://cdn.quilljs.com/1.3.7/quill.min.js',
    'quill/quill.snow.css' => 'https://cdn.quilljs.com/1.3.7/quill.snow.css',
    'html2pdf/html2pdf.bundle.min.js' => 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
];

foreach ($urls as $relativePath => $url) {
    $localPath = $libDir . '/' . $relativePath;
    $dir = dirname($localPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    echo "下载: $url\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'user_agent' => 'HIS Setup Script'
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        echo "  下载失败，跳过 (CDN 不可用)\n";
        continue;
    }
    
    file_put_contents($localPath, $content);
    echo "  已保存到: $localPath (" . strlen($content) . " 字节)\n";
}

echo "\n下载完成。本地库文件已保存到 public/lib/\n";
echo "如需从 CDN 回退，请删除 public/lib/ 目录。\n";
