<?php
// api/modules/admin/dict/save.php - 保存字典 (新增/修改)
Auth::requireRole(['admin']);
$key = trim((string)Validator::get('dkey', ''));
$name = trim((string)Validator::get('name', ''));
$items = Validator::get('items', []);
if (!is_array($items)) $items = [];
$items = array_values(array_filter(array_map(function ($x) { return trim((string)$x); }, $items), function ($x) { return $x !== ''; }));
if ($key === '' || $name === '') Response::error('缺少字典键或名称');
if (!count($items)) Response::error('字典选项不能为空');
if (!preg_match('/^[a-z0-9_]{2,50}$/i', $key)) Response::error('字典键仅限字母/数字/下划线');
if (in_array($key, ['p_name', 'p_age'], true)) Response::error('保留键');
$ok = emr_dict_save($key, $name, $items, (int)Auth::user()['id']);
Response::success(null, $ok ? '字典已保存' : '保存失败');
