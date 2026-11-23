<?php
// api/modules/admin/integration_audit_clear.php - 清空集成配置变更记录
Auth::requireRole(['admin']);
$n = DB::execute("DELETE FROM integration_audit");
Response::success(['cleared' => $n], '变更记录已清空');
