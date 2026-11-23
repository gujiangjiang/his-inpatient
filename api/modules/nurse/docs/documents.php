<?php
// api/modules/nurse/docs/documents.php - 常规护理文书 (入院评估/护理计划/健康宣教/转科记录等)
// 动作: list 查询 (admin/nurse/doctor 只读) | save 新增/更新 (admin/nurse) | delete 删除 (admin/nurse)
$action = Validator::get('action', 'list');
$user = Auth::user();

if ($action === 'list') {
    Auth::requireRole(['admin', 'nurse', 'doctor']);
    $patientId = Validator::get('patient_id');
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, (int)$patientId)) Response::error('无权访问该患者', 403);
    $rows = DB::select(
        "SELECT nd.*, u.name AS recorder_name
         FROM nursing_docs nd
         LEFT JOIN users u ON nd.recorded_by = u.id
         WHERE nd.patient_id = ?
         ORDER BY nd.created_at DESC, nd.id DESC", [$patientId]);
    Response::success($rows);
}

if ($action === 'save' || $action === 'delete') {
    Auth::requireRole(['admin', 'nurse']);
}

if ($action === 'save') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请选择患者');
    if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);
    $docType = trim((string)Validator::get('doc_type', ''));
    $title = trim((string)Validator::get('title', ''));
    $content = trim((string)Validator::get('content', ''));
    if ($docType === '') Response::error('请选择文书类型');
    if ($title === '') Response::error('请输入文书标题');
    if ($content === '') Response::error('请输入文书内容');

    $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) Response::error('患者不存在');

    $now = DateHelper::now();
    $userId = Auth::user()['id'];
    $id = (int)Validator::get('id', 0);
    $pdo = DB::getPDO();

    if ($id) {
        $exist = DB::selectOne("SELECT patient_id FROM nursing_docs WHERE id = ?", [$id]);
        if (!$exist) Response::error('文书不存在');
        if (!can_access_patient(Auth::user(), (int)$exist['patient_id'])) Response::error('无权操作该文书', 403);
        DB::execute("UPDATE nursing_docs SET doc_type = ?, title = ?, content = ?, recorded_by = ?, updated_at = ?
                     WHERE id = ?",
            [$docType, $title, $content, $userId, $now, $id]);
        Response::success(['id' => $id], '护理文书已更新');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO nursing_docs (patient_id, admission_no, doc_type, title, content, recorded_by, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$patientId, $patient['admission_no'], $docType, $title, $content, $userId, $now, $now]);
    Response::success(['id' => (int)$pdo->lastInsertId()], '护理文书已保存');
}

if ($action === 'delete') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    $row = DB::selectOne("SELECT patient_id FROM nursing_docs WHERE id = ?", [$id]);
    if (!$row) Response::error('文书不存在');
    if (!can_access_patient(Auth::user(), (int)$row['patient_id'])) Response::error('无权操作该文书', 403);
    DB::execute("DELETE FROM nursing_docs WHERE id = ?", [$id]);
    Response::success([], '已删除');
}