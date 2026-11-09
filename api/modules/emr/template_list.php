<?php
// api/modules/emr/template_list.php
$roles = ['admin', 'doctor'];
Auth::requireRole($roles);
/* record_type 仅在显式传入时过滤 (原默认 admission 导致按分类查询时
   病程/沟通/出院等分类永远查不到模板) */
$recordType = Validator::get('record_type');
$categoryId = Validator::get('category_id');
$structured = Validator::get('structured'); // 1 只取结构化模板(带schema_json)

$sql = "SELECT * FROM emr_templates WHERE 1=1";
$params = [];
if ($recordType) { $sql .= " AND record_type = ?"; $params[] = $recordType; }
if ($categoryId) { $sql .= " AND category_id = ?"; $params[] = $categoryId; }
if ($structured) { $sql .= " AND schema_json IS NOT NULL AND schema_json != ''"; }
$sql .= " ORDER BY usage_count DESC, created_at DESC";

$templates = DB::select($sql, $params);
// 解析 schema_json 供前端使用
foreach ($templates as &$t) {
    $t['schema'] = json_decode($t['schema_json'] ?? '{}', true) ?: null;
}
Response::success($templates);
