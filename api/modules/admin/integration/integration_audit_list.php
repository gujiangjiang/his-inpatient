<?php
// api/modules/admin/integration_audit_list.php - 集成配置变更记录 (最近 50 条)
Auth::requireRole(['admin']);
$rows = DB::select("SELECT * FROM integration_audit ORDER BY id DESC LIMIT 50");
Response::success($rows);
