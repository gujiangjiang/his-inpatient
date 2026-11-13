<?php
// api/modules/admin/integration_list.php - 对接端点列表
Auth::requireRole(['admin']);
$rows = DB::select("SELECT * FROM integration_endpoints ORDER BY id DESC");
Response::success($rows);
