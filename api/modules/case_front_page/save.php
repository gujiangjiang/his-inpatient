<?php
// api/modules/case_front_page/save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$admissionNo = Validator::get('admission_no');
$contentDelta = Validator::get('content_delta');

if (!$admissionNo || !$contentDelta) Response::error('缺少必要参数');

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

if ($id) {
    DB::execute("UPDATE case_front_page SET content_delta=?, content_html=?, updated_at=? WHERE id=?",
        [$contentDelta, $data['content_html'] ?? '', DateHelper::now(), $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO case_front_page (admission_no, content_delta, content_html, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$admissionNo, $contentDelta, $data['content_html'] ?? '', $userId, DateHelper::now(), DateHelper::now()]);
    $id = $pdo->lastInsertId();
}
Response::success(['id' => $id]);
