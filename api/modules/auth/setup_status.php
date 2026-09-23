<?php
// api/modules/auth/setup_status.php
$pdo = DB::getPDO();
$config = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'setup_completed'")->fetch();
$isSetup = $config ? ($config['config_value'] === '1') : false;

$hospConfig = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'hospital_name'")->fetch();

Response::success([
    'setup_completed' => $isSetup,
    'hospital_name' => $hospConfig ? $hospConfig['config_value'] : ''
]);
