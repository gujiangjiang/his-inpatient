<?php
// api/modules/admin/sys_config_list.php
Auth::requireRole(['admin']);
$pdo = DB::getPDO();
$stmt = $pdo->query("SELECT config_key, config_value, config_group, description FROM system_config
    WHERE config_key NOT IN ('setup_completed','emr_templates_version','hospital_brand_revision') ORDER BY config_key");
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
