<?php
// api/modules/pharmacy/medication_list.php
// 只读药品目录: 医生开嘱时亦需检索药品
Auth::requireRole(['admin', 'pharmacist', 'doctor']);
$sql = "SELECT * FROM medications ORDER BY code";
$stmt = DB::getPDO()->query($sql);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
