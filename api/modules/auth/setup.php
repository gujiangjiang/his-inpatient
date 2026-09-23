<?php
// api/modules/auth/setup.php
// 首次初始化：设置医院名称、组织机构代码、管理员账号/密码
$data = Validator::all();

$hospitalName = Validator::get('hospital_name');
$hospitalCode = Validator::get('hospital_code');
$username = Validator::get('username');
$password = Validator::get('password');
$password2 = Validator::get('password2');

if (!$hospitalName || !$hospitalCode || !$username || !$password || !$password2) {
    Response::error('请填写完整信息');
}
if ($password !== $password2) {
    Response::error('两次密码不一致');
}

// 检查是否已初始化
$config = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'setup_completed'");
if ($config && $config['config_value'] === '1') {
    Response::error('系统已初始化');
}

$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    // 系统配置
    DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
        ['setup_completed', '1', 'system', '系统是否已初始化', DateHelper::now()]);
    DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
        ['hospital_name', $hospitalName, 'system', '医院名称', DateHelper::now()]);
    DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
        ['hospital_code', $hospitalCode, 'system', '组织机构代码', DateHelper::now()]);

    // 创建管理员账号
    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, is_active, created_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), '系统管理员', 'admin', 1, DateHelper::now()]);

    $pdo->commit();
    Response::success(['admin_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('初始化失败：' . $e->getMessage());
}
