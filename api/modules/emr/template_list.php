<?php
// api/modules/emr/template_list.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
/* record_type 仅在显式传入时过滤 (原默认 admission 导致按分类查询时
   病程/沟通/出院等分类永远查不到模板) */
$recordType = Validator::get('record_type');
$categoryId = Validator::get('category_id');
$structured = Validator::get('structured'); // 1 只取结构化模板(带schema_json)

$sql = "SELECT t.*, dep.name AS department_name FROM emr_templates t LEFT JOIN departments dep ON dep.id = t.department_id WHERE 1=1";
$params = [];
if ($recordType) { $sql .= " AND t.record_type = ?"; $params[] = $recordType; }
if ($categoryId) { $sql .= " AND t.category_id = ?"; $params[] = $categoryId; }
if ($structured) { $sql .= " AND t.schema_json IS NOT NULL AND t.schema_json != ''"; }
$sql .= " ORDER BY t.usage_count DESC, t.created_at DESC";

$templates = DB::select($sql, $params);
// 解析 schema_json 供前端使用
foreach ($templates as &$t) {
    $t['schema'] = json_decode($t['schema_json'] ?? '{}', true) ?: null;
}
Response::success($templates);
