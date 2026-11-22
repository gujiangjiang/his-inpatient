<?php
// api/modules/nurse/patient_info.php - 护士站患者信息 (基本信息 + 身高体重补填)
// 动作: get 患者资料+首次护理记录(身高体重) | save 补填身高体重 (写入首次护理记录)
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$action = Validator::get('action', 'get');

if ($action === 'get') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, $patientId)) Response::error('无权访问该患者', 403);
    $p = DB::selectOne(
        "SELECT p.*, d.name AS department_name, w.name AS ward_name
         FROM patients p
         LEFT JOIN departments d ON p.department_id = d.id
         LEFT JOIN wards w ON p.ward_id = w.id
         WHERE p.id = ?", [$patientId]);
    if (!$p) Response::error('患者不存在');
    // 首次护理记录 (身高体重)
    $first = DB::selectOne("SELECT * FROM nursing_notes WHERE patient_id = ? AND is_first = 1 ORDER BY id DESC LIMIT 1", [$patientId]);
    $p['height'] = $first['height'] ?? null;
    $p['weight'] = $first['weight'] ?? null;
    Response::success($p);
}

if ($action === 'save') {
    $patientId = (int)Validator::get('patient_id', 0);
    if (!$patientId) Response::error('请选择患者');
    if (!can_access_patient($user, $patientId)) Response::error('无权操作该患者', 403);
    $height = Validator::get('height');
    $weight = Validator::get('weight');
    $phone = trim((string)Validator::get('phone', ''));
    $height = ($height === null || $height === '') ? null : (float)$height;
    $weight = ($weight === null || $weight === '') ? null : (float)$weight     ;
    $pdo = DB::getPDO();
    $now = DateHelper::now();
    $userId = $user['id'];

    // 更新联系电话 (patients.phone)
    if ($phone !== '') {
        DB::execute("UPDATE patients SET phone = ? WHERE id = ?", [$phone, $patientId]);
    }
    // 首次护理记录: 存在则更新; 不存在则创建一条 (补填身高体重)
    $first = DB::selectOne("SELECT * FROM nursing_notes WHERE patient_id = ? AND is_first = 1 ORDER BY id DESC LIMIT 1", [$patientId]);
    if ($first) {
        DB::execute("UPDATE nursing_notes SET height = ?, weight = ?, updated_at = ? WHERE id = ?",
            [$height, $weight, $now, $first['id']]);
    } else {
        $patient = DB::selectOne("SELECT admission_no FROM patients WHERE id = ?", [$patientId]);
        if (!$patient) Response::error('患者不存在');
        $stmt = $pdo->prepare(
            "INSERT INTO nursing_notes (patient_id, admission_no, record_time, category, content, note, is_first, height, weight, recorded_by, created_at)
             VALUES (?,?,?, 'first', '', ?, 1, ?, ?, ?, ?)");
        $stmt->execute([$patientId, $patient['admission_no'], $now, '首次护理记录（补填身高体重）', $height, $weight, $userId, $now]);
    }
    Response::success([], '身高体重已保存');
}
