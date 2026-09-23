<?php
// api/modules/emr/category_list.php
Auth::requireRole(['admin', 'doctor']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT * FROM emr_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));