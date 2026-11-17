<?php
// api/modules/auth/setup_status.php
require_once __DIR__ . '/../../core/branding.php';
try {
    $pdo = DB::getPDO();
    $config = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'setup_completed'")->fetch();
    $isSetup = $config ? ($config['config_value'] === '1') : false;

    Response::success(array_merge(['setup_completed' => $isSetup], branding_public()));
} catch (PDOException $e) {
    Response::success(array_merge(['setup_completed' => false], branding_public()));
}
