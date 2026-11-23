<?php
// api/modules/admin/user_list.php
Auth::requireRole(['admin']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT id, username, name, role, department_id, ward_id, is_active, created_at FROM users ORDER BY id DESC");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
