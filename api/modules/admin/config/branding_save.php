<?php
// api/modules/admin/config/branding_save.php - 上传/更新/删除医院 Logo 与 PWA PNG 图标
Auth::requireRole(['admin']);
require_once __DIR__ . '/../../../core/site/branding.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('仅支持 POST 请求', 405);
if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
    Response::error('非法请求', 403);
}

$action = (string)Validator::get('action', 'upload');
$paths = branding_paths();
if ($action === 'remove') {
    foreach ($paths as $path) if (is_file($path)) @unlink($path);
    branding_bump_revision();
    Response::success(branding_public(), '医院 Logo 已恢复为默认图标');
}
if ($action !== 'upload') Response::error('不支持的操作');

$specs = [
    'logo' => ['path' => $paths['logo'], 'exact' => 0],
    'icon192' => ['path' => $paths[192], 'exact' => 192],
    'icon512' => ['path' => $paths[512], 'exact' => 512],
];
$staged = [];
$validate = function ($file, $exact) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) Response::error('Logo 文件上传失败');
    if (!is_uploaded_file($file['tmp_name'])) Response::error('无效的上传文件');
    $size = (int)($file['size'] ?? 0);
    if ($size < 100 || $size > 5 * 1024 * 1024) Response::error('Logo 文件大小须在 100B 至 5MB 之间');
    if (!class_exists('finfo')) Response::error('服务器缺少 Fileinfo 扩展，无法校验图片');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->file($file['tmp_name']) !== 'image/png') Response::error('服务器仅接受浏览器转换后的 PNG 文件');
    $image = @getimagesize($file['tmp_name']);
    if (!$image || ($image[2] ?? 0) !== IMAGETYPE_PNG) Response::error('PNG 图片内容无效');
    $w = (int)$image[0]; $h = (int)$image[1];
    if ($exact && ($w !== $exact || $h !== $exact)) Response::error("PWA 图标必须为 {$exact}×{$exact}px");
    if (!$exact && ($w < 16 || $h < 16 || $w > 2048 || $h > 2048 || $w * $h > 4000000)) {
        Response::error('Logo 尺寸须在 16×16 至 2048×2048px 之间');
    }
};

try {
    $dir = branding_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) Response::error('无法创建品牌资源目录');
    foreach ($specs as $key => $spec) {
        $file = $_FILES[$key] ?? null;
        $validate($file, $spec['exact']);
        $tmp = $dir . '/.' . $key . '-' . bin2hex(random_bytes(6)) . '.tmp';
        if (!move_uploaded_file($file['tmp_name'], $tmp)) throw new RuntimeException('保存上传文件失败');
        $staged[$key] = $tmp;
    }
    foreach ($specs as $key => $spec) {
        if (is_file($spec['path'])) @unlink($spec['path']);
        if (!rename($staged[$key], $spec['path'])) throw new RuntimeException('更新品牌资源失败');
        @chmod($spec['path'], 0644);
        unset($staged[$key]);
    }
} catch (Throwable $e) {
    foreach ($staged as $tmp) if (is_file($tmp)) @unlink($tmp);
    Response::error($e->getMessage());
}

branding_bump_revision();
Response::success(branding_public(), '医院 Logo 已更新');
