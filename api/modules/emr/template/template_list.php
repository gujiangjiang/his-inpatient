<?php
// api/modules/emr/template/template_list.php - 模板列表 (发布可见性过滤)
Auth::requireRole(['admin', 'doctor']);
if (Validator::get('review') === '1' && Auth::user()['role'] !== 'admin') Response::error('无权限', 403);
$recordType = Validator::get('record_type');
$categoryId = Validator::get('category_id');
$structured = Validator::get('structured');
$review = Validator::get('review'); // 1: 审核中心 (仅管理员) 待审列表

[$pubSql, $pubParams] = emr_pub_visibility_sql('t', $review === '1');
$sql = "SELECT t.*, dep.name AS department_name, u.name AS creator_name FROM emr_templates t
        LEFT JOIN departments dep ON dep.id = t.department_id
        LEFT JOIN users u ON u.id = t.created_by
        WHERE 1=1" . $pubSql;
$params = $pubParams;
if ($recordType) { $sql .= " AND t.record_type = ?"; $params[] = $recordType; }
if ($categoryId) { $sql .= " AND t.category_id = ?"; $params[] = $categoryId; }
if ($structured) { $sql .= " AND t.schema_json IS NOT NULL AND t.schema_json != ''"; }
$sql .= " ORDER BY t.usage_count DESC, t.created_at DESC";

$templates = DB::select($sql, $params);
foreach ($templates as &$t) {
    $t['schema'] = json_decode($t['schema_json'] ?? '{}', true) ?: null;
}
Response::success($templates);