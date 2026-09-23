<?php
// api/modules/emr/document_sign.php
// 医师签名: 校验身份(密码) + 置为 signed 只读锁定
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$id = Validator::get('id');
$password = Validator::get('password');

if (!$id) Response::error('请输入文书ID');
if (!$password) Response::error('请输入密码确认身份');

$pdo = DB::getPDO();
$user = Auth::user();
$userId = $user['id'];

// 身份二次校验: 密码匹配当前登录医师
if (!password_verify($password, $user['password'])) {
    Response::error('身份验证失败, 无法签名', 403);
}

$doc = DB::selectOne("SELECT * FROM emr_documents WHERE id = ?", [$id]);
if (!$doc) Response::error('文书不存在');
if ($doc['status'] === 'signed') Response::error('文书已签名, 请勿重复签名');

$now = DateHelper::now();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE emr_documents SET status='signed', signer_id=?, signed_at=?, updated_at=? WHERE id=?");
    $stmt->execute([$userId, $now, $now, $id]);

    // 同步更新 doc_meta 中的签名信息
    $meta = json_decode($doc['doc_meta'] ?? '{}', true) ?: [];
    $meta['status'] = 'signed';
    $meta['signer_id'] = $userId;
    $meta['signed_at'] = $now;
    $pdo->prepare("UPDATE emr_documents SET doc_meta = ? WHERE id = ?")
        ->execute([json_encode($meta, JSON_UNESCAPED_UNICODE), $id]);

    $pdo->commit();
    Response::success([
        'id' => $id,
        'signed_at' => $now,
        'signer_name' => $user['name']
    ]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('签名失败: ' . $e->getMessage());
}