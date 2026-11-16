<?php
// api/modules/surgery/cancel.php - 取消手术申请 (取消后关联临时医嘱自动作废)
Auth::requireRole(['admin', 'doctor']);
$id = (int)Validator::get('id', 0);
$reason = trim((string)Validator::get('reason', ''));
if (!$id) Response::error('缺少手术申请');
$row = DB::selectOne("SELECT * FROM surgeries WHERE id = ?", [$id]);
if (!$row) Response::error('手术申请不存在', 404);
if (($row['status'] ?? '') === 'cancelled') Response::error('该手术申请已取消');
if (!can_access_patient(Auth::user(), (int)$row['patient_id'])) Response::error('权限不足', 403);
$user = Auth::user();
if ($user['role'] !== 'admin' && (int)($row['requested_by'] ?? 0) !== (int)$user['id']) {
    Response::error('仅申请人（或管理员）可取消该手术申请', 403);
}

$pdo = DB::getPDO();
$now = DateHelper::now();
$pdo->beginTransaction();
try {
    DB::execute("UPDATE surgeries SET status = 'cancelled', cancelled_at = ?, cancelled_by = ?, cancel_reason = ? WHERE id = ?",
        [$now, $user['id'], $reason, $id]);
    /* 关联手术医嘱作废 (手术医嘱不可在医嘱列表直接作废) */
    if (!empty($row['order_id'])) {
        DB::execute("UPDATE orders SET status = 'invalid', updated_at = ? WHERE id = ? AND status IN ('pending','verified','executing','invalid_pending')", [$now, (int)$row['order_id']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('取消失败：' . $e->getMessage());
}

/* 通知护士手术已取消 */
try {
    $patient = DB::selectOne("SELECT name, ward_id FROM patients WHERE id = ?", [(int)$row['patient_id']]);
    notify_send([
        'roles' => ['nurse'], 'ward_id' => ($patient && $patient['ward_id']) ? (int)$patient['ward_id'] : null,
        'type' => 'surgery',
        'title' => '手术申请已取消：' . (($patient && $patient['name']) ? $patient['name'] . ' · ' : '') . $row['surgery_name'],
        'body' => '取消人：' . $user['name'] . ($reason !== '' ? (' · 原因：' . $reason) : '') . ' · 关联手术医嘱已作废',
        'link' => '#nurse/station', 'ref_type' => 'surgery', 'ref_id' => $id,
    ]);
} catch (Exception $e) { /* ignore */ }

Response::success(['id' => $id]);
