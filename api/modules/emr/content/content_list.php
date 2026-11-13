<?php
// api/modules/emr/content/content_list.php - 内容库列表 (发布可见性过滤)
Auth::requireRole(['admin', 'doctor', 'nurse']);
if (Validator::get('review') === '1' && Auth::user()['role'] !== 'admin') Response::error('无权限', 403);
$categoryId = Validator::get('category_id');
$kw = trim((string)Validator::get('kw', ''));
$review = Validator::get('review'); // 1: 审核中心 (仅管理员) 待审列表

[$pubSql, $pubParams] = emr_pub_visibility_sql('c', $review === '1');
$sql = "SELECT c.*, cat.name AS category_name, dep.name AS department_name, u.name AS creator_name
        FROM emr_contents c
        LEFT JOIN emr_categories cat ON cat.id = c.category_id
        LEFT JOIN departments dep ON dep.id = c.department_id
        LEFT JOIN users u ON u.id = c.created_by
        WHERE 1=1" . $pubSql;
$params = $pubParams;
if ($categoryId) { $sql .= " AND c.category_id = ?"; $params[] = $categoryId; }
if ($kw !== '') { $sql .= " AND (c.name LIKE ? OR c.content LIKE ?)"; $params[] = '%' . $kw . '%'; $params[] = '%' . $kw . '%'; }
$sql .= " ORDER BY c.updated_at DESC, c.id DESC";

Response::success(DB::select($sql, $params));