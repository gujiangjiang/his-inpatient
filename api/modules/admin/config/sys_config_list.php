<?php
// api/modules/admin/sys_config_list.php
Auth::requireRole(['admin']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT config_key, config_value, config_group, description FROM system_config ORDER BY config_key");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
