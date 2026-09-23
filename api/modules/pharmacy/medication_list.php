<?php
// api/modules/pharmacy/medication_list.php
Auth::requireRole(['admin', 'pharmacist']);
$sql = "SELECT * FROM medications ORDER BY code";
$stmt = DB::getPDO()->query($sql);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
