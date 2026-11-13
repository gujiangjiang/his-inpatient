<?php
// 内容发布提交审核
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
$scope = Validator::get('scope');
if (!$id || !in_array($scope, ['department', 'all'], true)) Response::error('参数不完整');

$row = DB::selectOne("SELECT id, created_by, department_id FROM emr_contents WHERE id = ?", [$id]);
if (!$row) Response::error('内容不存在', 404);

$user = Auth::user();
if ($user['role'] !== 'admin' && (int)$row['created_by'] !== (int)$user['id']) Response::error('仅创建人可发布', 403);

$dept = $scope === 'department' ? ($user['department_id'] ?? $row['department_id']) : null;
DB::execute(
    "UPDATE emr_contents SET pub_status='pending', pub_scope=?, department_id=?, pub_audited_by=NULL, pub_audited_at=NULL, pub_reject_reason=NULL WHERE id=?",
    [$scope, $dept, $id]
);
Response::success(['id' => $id]);