<?php
// api/modules/nurse/nursing_notes.php - 护理记录单 (按时间序的护理观察/措施流水)
// 动作: list 查询 (admin/nurse/doctor 只读) | save 新增/更新 (admin/nurse) | delete 删除 (admin/nurse)
$action = Validator::get('action', 'list');
$user = Auth::user();

if ($action === 'list') {
    Auth::requireRole(['admin', 'nurse', 'doctor']);
    $patientId = Validator::get('patient_id');
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, (int)$patientId)) Response::error('无权访问该患者', 403);
    $rows = DB::select(
        "SELECT nn.*, u.name AS recorder_name
         FROM nursing_notes nn
         LEFT JOIN users u ON nn.recorded_by = u.id
         WHERE nn.patient_id = ?
         ORDER BY nn.record_time DESC, nn.id DESC", [$patientId]);
    Response::success($rows);
}

if ($action === 'save' || $action === 'delete') {
    Auth::requireRole(['admin', 'nurse']);
}

if ($action === 'save') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请选择患者');
    if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);
    $recordTime = trim((string)Validator::get('record_time', ''));
    $category = trim((string)Validator::get('category', 'observation'));
    $content = trim((string)Validator::get('content', ''));
    if ($recordTime === '') Response::error('请选择记录时间');
    if ($content === '') Response::error('请输入记录内容');

    $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) Response::error('患者不存在');

    $now = DateHelper::now();
    $userId = Auth::user()['id'];
    $fields = ['temperature', 'pulse', 'respiratory_rate', 'bp_systolic', 'bp_diastolic', 'intake_ml', 'output_ml'];
    $vals = [];
    foreach ($fields as $f) {
        $v = Validator::get($f);
        $vals[$f] = ($v === null || $v === '') ? null : $v;
    }

    $id = (int)Validator::get('id', 0);
    $pdo = DB::getPDO();
    if ($id) {
        $exist = DB::selectOne("SELECT patient_id FROM nursing_notes WHERE id = ?", [$id]);
        if (!$exist) Response::error('记录不存在');
        if (!can_access_patient(Auth::user(), (int)$exist['patient_id'])) Response::error('无权操作该记录', 403);
        $sets = ['record_time = ?', 'category = ?', 'content = ?', 'recorded_by = ?', 'updated_at = ?'];
        $params = [$recordTime, $category, $content, $userId, $now];
        foreach ($fields as $f) { $sets[] = "$f = ?"; $params[] = $vals[$f]; }
        $params[] = $id;
        DB::execute("UPDATE nursing_notes SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        Response::success(['id' => $id], '护理记录已更新');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO nursing_notes (patient_id, admission_no, record_time, category, content,
         temperature, pulse, respiratory_rate, bp_systolic, bp_diastolic, intake_ml, output_ml,
         recorded_by, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patientId, $patient['admission_no'], $recordTime, $category, $content,
        $vals['temperature'], $vals['pulse'], $vals['respiratory_rate'],
        $vals['bp_systolic'], $vals['bp_diastolic'], $vals['intake_ml'], $vals['output_ml'],
        $userId, $now
    ]);
    Response::success(['id' => (int)$pdo->lastInsertId()], '护理记录已保存');
}

if ($action === 'delete') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    $row = DB::selectOne("SELECT patient_id FROM nursing_notes WHERE id = ?", [$id]);
    if (!$row) Response::error('记录不存在');
    if (!can_access_patient(Auth::user(), (int)$row['patient_id'])) Response::error('无权操作该记录', 403);
    DB::execute("DELETE FROM nursing_notes WHERE id = ?", [$id]);
    Response::success([], '已删除');
}