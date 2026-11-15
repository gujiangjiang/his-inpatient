<?php
// api/modules/emr/document/maintenance_request.php - 签名病历维护申请 (编辑/删除)
// 仅已签名病历可申请; 同一文书只允许一条待审核申请
Auth::requireRole(['admin', 'doctor']);
$docId = (int)Validator::get('doc_id', 0);
$action = Validator::get('action');
$reason = trim((string)Validator::get('reason', ''));
if (!$docId || !in_array($action, ['edit', 'delete'], true)) Response::error('参数不完整');
if ($reason === '') Response::error('请填写维护原因');

$doc = DB::selectOne("SELECT id, status, patient_id, title FROM emr_documents WHERE id = ?", [$docId]);
if (!$doc) Response::error('文书不存在', 404);
if ($doc['status'] !== 'signed') Response::error('仅已签名病历需要维护申请', 400);

$pending = DB::selectOne(
    "SELECT id FROM emr_maintenance_requests WHERE doc_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
    [$docId]
);
if ($pending) Response::error('该病历已有待审核的维护申请，请等待管理员处理', 400);

$uid = Auth::user()['id'];
$pdo = DB::getPDO();
$pdo->prepare("INSERT INTO emr_maintenance_requests (doc_id, action, reason, status, requested_by, requested_at) VALUES (?,?,?,'pending',?,?)")
    ->execute([$docId, $action, $reason, $uid, DateHelper::now()]);
$reqId = (int)$pdo->lastInsertId();
/* 推送: 通知管理员病历维护申请 */
notify_send([
    'roles' => ['admin'], 'exclude_ids' => [$uid],
    'type' => 'maintenance',
    'title' => '病历维护申请（' . ($action === 'delete' ? '删除' : '编辑') . '）：' . (string)$doc['title'],
    'body' => Auth::user()['name'] . ' · ' . $reason,
    'link' => '#admin/review', 'ref_type' => 'maintenance', 'ref_id' => $reqId, 'upsert' => true,
]);
Response::success(['id' => $reqId]);
