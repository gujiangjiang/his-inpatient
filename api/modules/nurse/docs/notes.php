<?php
// api/modules/nurse/docs/notes.php - 护理记录单 (按时间序的护理观察/措施流水)
// 护理记录单是生命体征/护理观察唯一数据源; 体温单从此表派生
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
         ORDER BY nn.record_time ASC, nn.id ASC", [$patientId]);
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
    if ($recordTime === '') Response::error('请选择记录时间');
    /* 记录时间为站点时区墙钟输入 → 按存储模式换算落库 (时间铁律; 体温单/列表按本地时区统一读出) */
    $recordTime = TimeHelper::fromLocal($recordTime);
    if ($recordTime === '') Response::error('记录时间格式错误');

    $id = (int)Validator::get('id', 0);
    $isFirst = (int)Validator::get('is_first', 0) ? 1 : 0;
    $category = trim((string)Validator::get('category', $isFirst ? 'first' : 'observation'));
    // 护理记录文本书写: 优先 note, 回退 content (兼容旧数据)
    $noteText = trim((string)Validator::get('note', ''));
    $content = trim((string)Validator::get('content', ''));
    if ($noteText === '') $noteText = $content;
    if ($isFirst && $noteText === '') Response::error('请填写首次护理记录内容');

    $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) Response::error('患者不存在');

    // 首次护理记录: 每人仅一条
    if ($isFirst) {
        $existFirst = DB::selectOne("SELECT id FROM nursing_notes WHERE patient_id = ? AND is_first = 1 AND id <> ?", [$patientId, $id]);
        if ($existFirst) Response::error('该患者已存在首次护理记录');
    }

    // 选填数值/文本字段 (含体温单三测单所需: 疼痛/降温复测/心率/起搏器/呼吸机/小便量)
    $numFields = ['temperature', 'pulse', 'respiratory_rate', 'bp_systolic', 'bp_diastolic',
        'intake_ml', 'output_ml', 'oxygen_saturation', 'drainage_ml', 'blood_glucose',
        'gcs_eye', 'gcs_verbal', 'gcs_motor',
        'pain_score', 'cooling_temp', 'heart_rate', 'urine_ml', 'pacemaker', 'ventilator'];
    $txtFields = ['has_pipeline', 'pipeline_desc', 'consciousness', 'pupil',
        'temp_type', 'stool', 'skin_test', 'allergy_drugs', 'special_drugs', 'other_remark'];
    $vals = [];
    foreach ($numFields as $f) {
        $v = Validator::get($f);
        $vals[$f] = ($v === null || $v === '') ? null : $v;
    }
    foreach ($txtFields as $f) {
        $v = Validator::get($f);
        $vals[$f] = ($v === null || $v === '') ? '' : trim((string)$v);
    }
    // 首次护理记录: 身高体重
    $vals['height'] = null;
    $vals['weight'] = null;
    if ($isFirst) {
        $h = Validator::get('height');
        $w = Validator::get('weight');
        $vals['height'] = ($h === null || $h === '') ? null : $h;
        $vals['weight'] = ($w === null || $w === '') ? null : $w;
    }

    $now = DateHelper::now();
    $userId = Auth::user()['id'];
    $allFields = array_merge(['record_time', 'category', 'content', 'note', 'is_first'], $numFields, $txtFields, ['height', 'weight']);

    $pdo = DB::getPDO();
    if ($id) {
        $exist = DB::selectOne("SELECT patient_id FROM nursing_notes WHERE id = ?", [$id]);
        if (!$exist) Response::error('记录不存在');
        if (!can_access_patient(Auth::user(), (int)$exist['patient_id'])) Response::error('无权操作该记录', 403);
        $sets = ['record_time = ?', 'category = ?', 'content = ?', 'note = ?', 'is_first = ?', 'recorded_by = ?', 'updated_at = ?'];
        $params = [$recordTime, $category, $content, $noteText, $isFirst, $userId, $now];
        foreach ($numFields as $f) { $sets[] = "$f = ?"; $params[] = $vals[$f]; }
        foreach ($txtFields as $f) { $sets[] = "$f = ?"; $params[] = $vals[$f]; }
        $sets[] = 'height = ?'; $params[] = $vals['height'];
        $sets[] = 'weight = ?'; $params[] = $vals['weight'];
        $params[] = $id;
        DB::execute("UPDATE nursing_notes SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        Response::success(['id' => $id], '护理记录已更新');
    }

    $ph = implode(',', array_fill(0, count($allFields), '?'));
    $stmt = $pdo->prepare("INSERT INTO nursing_notes (patient_id, admission_no, " . implode(', ', $allFields) . ", recorded_by, created_at)
        VALUES (?,?," . $ph . ",?,?)");
    $ins = [];
    foreach ($allFields as $f) {
        if ($f === 'record_time') $ins[] = $recordTime;
        elseif ($f === 'category') $ins[] = $category;
        elseif ($f === 'content') $ins[] = $content;
        elseif ($f === 'note') $ins[] = $noteText;
        elseif ($f === 'is_first') $ins[] = $isFirst;
        else $ins[] = $vals[$f];
    }
    $stmt->execute(array_merge([$patientId, $patient['admission_no']], $ins, [$userId, $now]));
    Response::success(['id' => (int)$pdo->lastInsertId()], $isFirst ? '首次护理记录已保存' : '护理记录已保存');
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