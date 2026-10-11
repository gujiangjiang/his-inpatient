<?php
// api/modules/consult/consult_reply.php - 回复会诊意见, 原医嘱自动标注"已会诊"
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$id = (int)Validator::get('id', 0);
$reply = trim((string)Validator::get('reply_text', ''));
if (!$id || $reply === '') Response::error('请填写会诊意见');

$consult = DB::selectOne("SELECT * FROM consultations WHERE id = ?", [$id]);
if (!$consult) Response::error('会诊不存在', 404);
if ($consult['status'] !== 'pending') Response::error('该会诊已回复');

if ($user['role'] !== 'admin') {
    $deptIds = allowed_department_ids($user);
    if (!in_array((int)$consult['to_dept_id'], $deptIds, true)) Response::error('无权回复该会诊', 403);
}

$now = DateHelper::now();
$pdo = DB::getPDO();
$pdo->beginTransaction();
try {
    DB::execute(
        "UPDATE consultations SET status = 'replied', reply_text = ?, replied_by = ?, replied_by_name = ?, replied_at = ? WHERE id = ? AND status = 'pending'",
        [$reply, $user['id'], $user['name'], $now, $id]
    );
    if ($consult['order_id']) {
        DB::execute("UPDATE orders SET consult_status = 'replied', updated_at = ? WHERE id = ?", [$now, $consult['order_id']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('回复失败: ' . $e->getMessage(), 500);
}
Response::success(null, '会诊意见已回传, 原医嘱已标注「已会诊」');
