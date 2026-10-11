<?php
// api/modules/lab/lab_panel_save.php - 保存常用检验套组模板
Auth::requireRole(['admin', 'doctor']);
$name = trim((string)Validator::get('name', ''));
$itemIds = Validator::get('item_ids', []);
if ($name === '' || !is_array($itemIds) || !count($itemIds)) Response::error('请填写套组名称并选择项目');
$user = Auth::user();
DB::insert('lab_panels', [
    'name' => $name,
    'items_json' => json_encode(array_values(array_map('intval', $itemIds))),
    'created_by' => $user['id'],
    'dept_id' => $user['department_id'],
    'created_at' => DateHelper::now(),
]);
Response::success(null, '套组已保存');
