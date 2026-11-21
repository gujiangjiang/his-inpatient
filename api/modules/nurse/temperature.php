<?php
// api/modules/nurse/temperature.php - 体温单 (结构化录入/查询)
// 动作: list 查询某患者体温记录 | save 录入单条时点数据 | delete 删除单条
Auth::requireRole(['admin', 'nurse']);
$action = Validator::get('action', 'list');

if ($action === 'list') {
    $patientId = Validator::get('patient_id');
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient(Auth::user(), (int)$patientId)) Response::error('无权访问该患者', 403);
    $rows = DB::select(
        "SELECT tc.*, u.name AS recorder_name
         FROM temperature_charts tc
         LEFT JOIN users u ON tc.recorded_by = u.id
         WHERE tc.patient_id = ?
         ORDER BY tc.record_date DESC, tc.time_slot ASC", [$patientId]);
    Response::success($rows);
}

if ($action === 'save') {
    $data = Validator::all();
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请选择患者');
    if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权操作该患者', 403);
    $recordDate = trim((string)Validator::get('record_date', ''));
    $timeSlot = trim((string)Validator::get('time_slot', ''));
    if ($recordDate === '') Response::error('请选择记录日期');
    if ($timeSlot === '') Response::error('请选择时点');

    $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) Response::error('患者不存在');

    // 同一患者同一日期同时点已存在则覆盖更新 (体温单每天固定时点)
    $exist = DB::selectOne("SELECT id FROM temperature_charts
        WHERE patient_id = ? AND record_date = ? AND time_slot = ?", [$patientId, $recordDate, $timeSlot]);

    $fields = ['temperature', 'pulse', 'respiratory_rate', 'bp_systolic', 'bp_diastolic',
               'oxygen_saturation', 'intake_ml', 'output_ml', 'notes'];
    $vals = [];
    foreach ($fields as $f) {
        $v = Validator::get($f);
        $vals[$f] = ($v === null || $v === '') ? null : $v;
    }
    $now = DateHelper::now();
    $userId = Auth::user()['id'];

    if ($exist) {
        $sets = [];
        $params = [];
        foreach ($fields as $f) { $sets[] = "$f = ?"; $params[] = $vals[$f]; }
        $sets[] = "notes = ?"; $params[] = $vals['notes'];
        $sets[] = "recorded_by = ?"; $params[] = $userId;
        $sets[] = "updated_at = ?"; $params[] = $now;
        $params[] = $exist['id'];
        DB::execute("UPDATE temperature_charts SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        Response::success(['id' => (int)$exist['id']], '体温单已更新');
    }

    $pdo = DB::getPDO();
    $stmt = $pdo->prepare(
        "INSERT INTO temperature_charts (patient_id, admission_no, record_date, time_slot,
         temperature, pulse, respiratory_rate, bp_systolic, bp_diastolic, oxygen_saturation,
         intake_ml, output_ml, notes, recorded_by, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patientId, $patient['admission_no'], $recordDate, $timeSlot,
        $vals['temperature'], $vals['pulse'], $vals['respiratory_rate'],
        $vals['bp_systolic'], $vals['bp_diastolic'], $vals['oxygen_saturation'],
        $vals['intake_ml'], $vals['output_ml'], $vals['notes'], $userId, $now
    ]);
    Response::success(['id' => (int)$pdo->lastInsertId()], '体温单已保存');
}

if ($action === 'delete') {
    $id = (int)Validator::get('id', 0);
    if (!$id) Response::error('参数错误');
    $row = DB::selectOne("SELECT patient_id FROM temperature_charts WHERE id = ?", [$id]);
    if (!$row) Response::error('记录不存在');
    if (!can_access_patient(Auth::user(), (int)$row['patient_id'])) Response::error('无权操作该记录', 403);
    DB::execute("DELETE FROM temperature_charts WHERE id = ?", [$id]);
    Response::success([], '已删除');
}