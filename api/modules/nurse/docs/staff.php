<?php
// api/modules/nurse/docs/staff.php - 护士名单 (护理文书签名/宣教者下拉数据源)
// 仅返回在职护士的 id/name, 不暴露账号等敏感字段; 病区筛选可选
Auth::requireRole(['admin', 'nurse', 'doctor']);
$wardId = (int)Validator::get('ward_id', 0);
$sql = "SELECT id, name, ward_id FROM users WHERE role = 'nurse' AND is_active = 1";
$params = [];
if ($wardId) { $sql .= " AND (ward_id = ? OR ward_id IS NULL)"; $params[] = $wardId; }
$sql .= " ORDER BY CASE WHEN ward_id = ? THEN 0 ELSE 1 END, name";
$params[] = $wardId ?: 0;
$rows = DB::select($sql, $params);
Response::success($rows);
