<?php
// api/modules/admin/department_list.php
Auth::requireRole(['admin', 'doctor', 'nurse']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT * FROM departments ORDER BY id DESC");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
