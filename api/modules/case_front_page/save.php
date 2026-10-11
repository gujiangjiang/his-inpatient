<?php
// api/modules/case_front_page/save.php - 病案首页保存 (结构化 data_json; 兼容旧 content_delta)
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();
$admissionNo = Validator::get('admission_no');

if (!$admissionNo) Response::error('缺少住院号');

$pdo = DB::getPDO();
$userId = Auth::user()['id'];
$homeData = Validator::get('data');
$contentDelta = Validator::get('content_delta');

$existing = DB::selectOne("SELECT id FROM case_front_page WHERE admission_no = ? ORDER BY id DESC LIMIT 1", [$admissionNo]);

if ($homeData !== null) {
    // 结构化病案首页
    $patient = DB::selectOne("SELECT id FROM patients WHERE admission_no = ?", [$admissionNo]);
    if (!$patient) Response::error('患者不存在', 404);
    $json = json_encode($homeData, JSON_UNESCAPED_UNICODE);
    if ($existing) {
        DB::execute("UPDATE case_front_page SET data_json=?, updated_at=? WHERE id=?",
            [$json, DateHelper::now(), $existing['id']]);
        $id = $existing['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO case_front_page (patient_id, admission_no, data_json, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$patient['id'], $admissionNo, $json, $userId, DateHelper::now(), DateHelper::now()]);
        $id = $pdo->lastInsertId();
    }
    Response::success(['id' => $id]);
}

// 旧版富文本保存路径
if (!$contentDelta) Response::error('缺少必要参数');
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
