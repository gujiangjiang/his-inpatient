<?php
// api/modules/emr/content_list.php - 病历内容库列表 (纯文本条目)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$categoryId = Validator::get('category_id');
$kw = trim((string)Validator::get('kw', ''));

$sql = "SELECT c.*, cat.name AS category_name FROM emr_contents c
        LEFT JOIN emr_categories cat ON cat.id = c.category_id WHERE 1=1";
$params = [];
if ($categoryId) { $sql .= " AND c.category_id = ?"; $params[] = $categoryId; }
if ($kw !== '') { $sql .= " AND (c.name LIKE ? OR c.content LIKE ?)"; $params[] = '%' . $kw . '%'; $params[] = '%' . $kw . '%'; }
$sql .= " ORDER BY c.updated_at DESC, c.id DESC";

Response::success(DB::select($sql, $params));
