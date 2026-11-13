<?php
// api/modules/admin/api_config_list.php
Auth::requireRole(['admin']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT * FROM api_configs ORDER BY id DESC");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
