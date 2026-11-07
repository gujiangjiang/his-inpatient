<?php
// api/modules/emr/document_sign.php
// 医师签名: 校验身份(密码) + 置为 signed 只读锁定
// 字处理文书: 将签名写入 dyn(sign_names) —— 上级医师在前、本人其后(去重), 并同步打印快照
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

// 签名文本: 上级医师(患者指派)在前, 本人签名其后; 同名去重
$signNames = (string)$user['name'];
try {
    $assign = DB::selectOne(
        "SELECT u.name AS senior_name FROM patient_assignments pa
         LEFT JOIN users u ON u.id = pa.senior_doctor_id
         WHERE pa.patient_id = ? LIMIT 1",
        [$doc['patient_id']]
    );
    $senior = $assign ? trim((string)$assign['senior_name']) : '';
    $names = [];
    if ($senior !== '') $names[] = $senior;
    if (!in_array((string)$user['name'], $names, true)) $names[] = (string)$user['name'];
    $signNames = implode(' ', $names);
} catch (Exception $e) { /* 无指派信息: 仅本人签名 */ }

// 字处理文书: 将签名写入 dyn 动态项 + 打印快照 (签名后即为事实, 不可再改)
$docJsonStr = $doc['doc_json'] ?? '';
$contentHtml = $doc['content_html'] ?? '';
if ($docJsonStr !== '') {
    $dj = json_decode($docJsonStr, true);
    if (is_array($dj) && is_array($dj['body'] ?? null)) {
        $walk = function (&$blocks) use (&$walk, $signNames) {
            foreach ($blocks as &$b) {
                if (($b['t'] ?? '') === 'p') {
                    if (isset($b['runs']) && is_array($b['runs'])) {
                        foreach ($b['runs'] as &$it) {
                            if (($it['t'] ?? '') === 'dyn' && ($it['key'] ?? '') === 'sign_names') $it['text'] = $signNames;
                        }
                        unset($it);
                    }
                } elseif (($b['t'] ?? '') === 'tbl' && isset($b['rows']) && is_array($b['rows'])) {
                    foreach ($b['rows'] as &$r) {
                        if (!isset($r['cells']) || !is_array($r['cells'])) continue;
                        foreach ($r['cells'] as &$c) {
                            if (isset($c['blocks']) && is_array($c['blocks'])) $walk($c['blocks']);
                        }
                        unset($c);
                    }
                    unset($r);
                }
            }
            unset($b);
        };
        $walk($dj['body']);
        $docJsonStr = json_encode($dj, JSON_UNESCAPED_UNICODE);
    }
}
if ($contentHtml !== '' && strpos($contentHtml, 'data-key="sign_names"') !== false) {
    $contentHtml = preg_replace_callback(
        '/(data-key="sign_names"[^>]*>)[^<]*/u',
        function ($m) use ($signNames) { return $m[1] . $signNames; },
        $contentHtml
    );
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE emr_documents SET status='signed', signer_id=?, signed_at=?, updated_at=?, doc_json=?, content_html=? WHERE id=?");
    $stmt->execute([$userId, $now, $now, $docJsonStr, $contentHtml, $id]);

    // 同步更新 doc_meta 中的签名信息
    $meta = json_decode($doc['doc_meta'] ?? '{}', true) ?: [];
    $meta['status'] = 'signed';
    $meta['signer_id'] = $userId;
    $meta['signed_at'] = $now;
    $pdo->prepare("UPDATE emr_documents SET doc_meta = ? WHERE id = ?")
        ->execute([json_encode($meta, JSON_UNESCAPED_UNICODE), $id]);

    // 签名固化为不可变版本 (字处理编辑器文档, 含已写入的签名)
    if ($docJsonStr !== '') {
        $pdo->prepare("INSERT INTO emr_document_versions (doc_id, doc_json, content_html, action, created_by, created_at) VALUES (?,?,?, 'sign', ?, ?)")
            ->execute([$id, $docJsonStr, $contentHtml, $userId, $now]);
    }

    $pdo->commit();
    Response::success([
        'id' => $id,
        'signed_at' => $now,
        'signer_name' => $user['name'],
        'sign_names' => $signNames
    ]);
} catch (Exception $e) {
    $pdo->rollback();
    Response::error('签名失败: ' . $e->getMessage());
}