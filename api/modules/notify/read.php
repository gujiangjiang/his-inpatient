<?php
// api/modules/notify/read.php - 标记已读 (ids 或 all=1)
Auth::requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']);
$ids = Validator::get('ids', []);
$all = (int)Validator::get('all', 0) === 1;
notify_mark_read((int)Auth::user()['id'], is_array($ids) ? $ids : [], $all);
Response::success(null, '已标记');
