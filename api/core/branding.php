<?php
// api/core/branding.php - 医院名称/Logo/PWA 品牌信息的统一读取与固定文件路径

function branding_config_value($key, $default = '')
{
    try {
        $row = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = ?", [$key]);
        return $row ? (string)$row['config_value'] : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function branding_save_config($key, $value, $group = 'system', $description = '')
{
    $row = DB::selectOne("SELECT config_key FROM system_config WHERE config_key = ?", [$key]);
    $now = gmdate('Y-m-d H:i:s');
    if ($row) {
        DB::execute("UPDATE system_config SET config_value = ?, updated_at = ? WHERE config_key = ?", [$value, $now, $key]);
    } else {
        DB::execute(
            "INSERT INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
            [$key, $value, $group, $description, $now]
        );
    }
}

function branding_upload_dir()
{
    return BASE_DIR . '/public/uploads/branding';
}

function branding_paths()
{
    $dir = branding_upload_dir();
    return [
        'logo' => $dir . '/hospital-logo.png',
        192 => $dir . '/hospital-icon-192.png',
        512 => $dir . '/hospital-icon-512.png',
    ];
}

function branding_default_icon_path($size)
{
    $size = ((int)$size >= 512) ? 512 : 192;
    return BASE_DIR . '/public/icons/brand/default-hospital-' . $size . '.png';
}

function branding_revision()
{
    $value = branding_config_value('hospital_brand_revision', '');
    if ($value !== '') return preg_replace('/[^0-9A-Za-z_.-]/', '', $value);
    $mtime = 0;
    foreach (branding_paths() as $path) if (is_file($path)) $mtime = max($mtime, (int)filemtime($path));
    return $mtime ? (string)$mtime : (defined('APP_VERSION') ? APP_VERSION : '1');
}

function branding_bump_revision()
{
    $value = (string)round(microtime(true) * 1000);
    branding_save_config('hospital_brand_revision', $value, 'internal', '医院品牌资源版本');
    return $value;
}

function branding_has_custom_logo()
{
    $paths = branding_paths();
    return is_file($paths['logo']);
}

function branding_icon_path($size)
{
    $size = ((int)$size >= 512) ? 512 : 192;
    $paths = branding_paths();
    return is_file($paths[$size]) ? $paths[$size] : branding_default_icon_path($size);
}

function branding_names()
{
    $name = trim(branding_config_value('hospital_name', ''));
    if ($name === '') $name = '开源通用医院信息系统';
    return [
        'hospital_name' => $name,
        'hospital_name2' => trim(branding_config_value('hospital_name2', '')),
    ];
}

function branding_public()
{
    $names = branding_names();
    $revision = branding_revision();
    $custom = branding_has_custom_logo();
    return array_merge($names, [
        'has_custom_logo' => $custom,
        'brand_revision' => $revision,
        'logo_url' => $custom
            ? '/uploads/branding/hospital-logo.png?v=' . rawurlencode($revision)
            : '/icons/brand/default-hospital.svg?v=' . rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1'),
        'icon_192_url' => '/branding/icon?size=192&v=' . rawurlencode($revision),
        'icon_512_url' => '/branding/icon?size=512&v=' . rawurlencode($revision),
    ]);
}
