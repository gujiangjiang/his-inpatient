<?php
// api/modules/admin/ward_list.php
Auth::requireRole(['admin']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT * FROM wards ORDER BY id DESC");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
