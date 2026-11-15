<?php
// 模板发布提交审核
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
$scope = Validator::get('scope');
if (!$id || !in_array($scope, ['department', 'all'], true)) Response::error('参数不完整');

$row = DB::selectOne("SELECT id, created_by, department_id FROM emr_templates WHERE id = ?", [$id]);
if (!$row) Response::error('模板不存在', 404);

$user = Auth::user();
if ($user['role'] !== 'admin' && (int)$row['created_by'] !== (int)$user['id']) Response::error('仅创建人可发布', 403);

$dept = $scope === 'department' ? ($user['department_id'] ?? $row['department_id']) : null;
DB::execute(
    "UPDATE emr_templates SET pub_status='pending', pub_scope=?, department_id=?, pub_audited_by=NULL, pub_audited_at=NULL, pub_reject_reason=NULL WHERE id=?",
    [$scope, $dept, $id]
);
/* 推送: 通知管理员待审核 (发布人重发时原地更新) */
$tplName = (string)(DB::selectOne("SELECT name FROM emr_templates WHERE id = ?", [$id])['name'] ?? '');
notify_send([
    'roles' => ['admin'], 'exclude_ids' => [(int)$user['id']],
    'type' => 'template', 'title' => '模板发布待审核：' . $tplName,
    'body' => $user['name'] . ' 提交了' . ($scope === 'all' ? '全院用' : '科室用') . '模板',
    'link' => '#admin/review', 'ref_type' => 'template', 'ref_id' => (int)$id, 'upsert' => true,
]);
Response::success(['id' => $id]);