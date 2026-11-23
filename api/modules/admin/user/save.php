<?php
// api/modules/admin/user_save.php
Auth::requireRole(['admin']);
$data = Validator::all();
$username = Validator::get('username');
$name = Validator::get('name');
$role = Validator::get('role');

if (!$username || !$name || !$role) {
    Response::error('请输入用户名、姓名和角色');
}

$pdo = DB::getPDO();
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    // 编辑用户
    $fields = [
        'name' => $name,
        'role' => $role,
        'department_id' => $data['department_id'] ?? null,
        'ward_id' => $data['ward_id'] ?? null,
        'is_active' => $data['is_active'] ?? 1,
    ];
    // 密码处理：如果提供了新密码则更新
    $setParts = [];
    $values = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "$col = ?";
        $values[] = $val;
    }
    if (!empty($data['password'])) {
        $setParts[] = "password = ?";
        $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    $values[] = $id;
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?");
    $stmt->execute($values);
} else {
    // 创建用户
    if (empty($data['password'])) {
        Response::error('创建用户时必须设置密码');
    }
    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, department_id, ward_id, is_active, created_at) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $username,
        password_hash($data['password'], PASSWORD_DEFAULT),
        $name,
        $role,
        $data['department_id'] ?? null,
        $data['ward_id'] ?? null,
        $data['is_active'] ?? 1,
        DateHelper::now()
    ]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
