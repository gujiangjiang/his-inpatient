<?php
// api/modules/patients/patient_save.php
Auth::requireRole(['admin', 'doctor']);
$data = Validator::all();

$name = Validator::get('name');
$gender = Validator::get('gender');
$birthDate = Validator::get('birth_date');
$departmentId = Validator::get('department_id');
$wardId = Validator::get('ward_id');

if (!$name || !$gender || !$birthDate || !$departmentId || !$wardId) {
    Response::error('请填写完整患者信息');
}

$id = isset($data['id']) && $data['id'] ? $data['id'] : null;

// 护理等级由医生护理医嘱决定; 编辑未显式提供时保留原值 (新建默认三级)
$nursingLevel = Validator::get('nursing_level', '');
if ($nursingLevel === '' || $nursingLevel === null) {
    $nursingLevel = $id ? (string)(DB::selectOne("SELECT nursing_level FROM patients WHERE id = ?", [$id])['nursing_level'] ?? '3') : '3';
}
$feeType = Validator::get('fee_type', '');
$allergyHistory = Validator::get('allergy_history', '');
$vteCaprini = Validator::get('vte_caprini', '');
$vteBleeding = Validator::get('vte_bleeding', '');
$isCritical = (int)Validator::get('is_critical', 0);

// 数据权限: 医生只能向授权科室登记/调整患者 (编辑时患者当前科室同样须在授权范围, 防跨科室搬移)
$currentUser = Auth::user();
if ($currentUser['role'] === 'doctor') {
    if (!can_access_department($currentUser, $departmentId)) {
        Response::error('无权操作该科室', 403);
    }
    if ($id) {
        $curDept = (int)(DB::selectOne("SELECT department_id FROM patients WHERE id = ?", [$id])['department_id'] ?? 0);
        if ($curDept && !can_access_department($currentUser, $curDept)) {
            Response::error('无权操作其他科室的患者', 403);
        }
    }
}

$pdo = DB::getPDO();

if ($id) {
    $existingFunds = DB::selectOne("SELECT prepay_fee, overdraft_limit FROM patients WHERE id = ?", [$id]);
    $prepay = array_key_exists('prepay_fee', $data) ? (float)$data['prepay_fee'] : ($existingFunds ? (float)$existingFunds['prepay_fee'] : 0.0);
    $overdraft = array_key_exists('overdraft_limit', $data) ? (float)$data['overdraft_limit'] : ($existingFunds ? (float)$existingFunds['overdraft_limit'] : 1000.0);
    $stmt = $pdo->prepare("UPDATE patients SET name=?, gender=?, birth_date=?, id_card=?, department_id=?, ward_id=?, bed_no=?, admission_date=?, admission_diagnosis=?, admission_icd_code=?, nursing_level=?, fee_type=?, allergy_history=?, vte_caprini=?, vte_bleeding=?, is_critical=?, prepay_fee=?, overdraft_limit=? WHERE id=?");
    $stmt->execute([
        $name, $gender, $birthDate,
        $data['id_card'] ?? '',
        $departmentId, $wardId,
        $data['bed_no'] ?? '',
        $data['admission_date'] ?? DateHelper::now(),
        $data['admission_diagnosis'] ?? '',
        $data['admission_icd_code'] ?? '',
        $nursingLevel, $feeType, $allergyHistory, $vteCaprini, $vteBleeding, $isCritical,
        $prepay, $overdraft,
        $id
    ]);
    /* 修改患者自带诊断: 同步门(急)诊诊断行 (无更高级诊断时主档展示随之更新) */
    try {
        $seedName = trim((string)($data['admission_diagnosis'] ?? ''));
        if ($seedName !== '') emr_diag_seed_outpatient((int)$id, '', $seedName, (string)($data['admission_icd_code'] ?? ''));
        emr_diag_recompute_patient((int)$id);
    } catch (Exception $e) { /* 同步失败不阻断保存 */ }
    Response::success(['id' => $id]);
} else {
    /* 患者号与住院号同尾号 (既有可观察行为保持); 当日最大尾号+1 替代 COUNT+1 防复用撞唯一索引 */
    $noTail = seq_serial_tail('patients', 'patient_no', 'P');
    $patientNo = 'P' . date('Ymd') . $noTail;
    $admissionNo = 'A' . date('Ymd') . $noTail;

    if (!empty($data['bed_no'])) {
        $bedStmt = $pdo->prepare("SELECT * FROM beds WHERE ward_id = ? AND bed_no = ? AND status = 'occupied'");
        $bedStmt->execute([$wardId, $data['bed_no']]);
        if ($bedStmt->fetch()) {
            Response::error('床位已被占用');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO patients (patient_no, name, gender, birth_date, id_card, admission_no, department_id, ward_id, bed_no, admission_date, admission_diagnosis, admission_icd_code, nursing_level, fee_type, allergy_history, vte_caprini, vte_bleeding, is_critical, prepay_fee, overdraft_limit, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patientNo, $name, $gender, $birthDate,
        $data['id_card'] ?? '',
        $admissionNo, $departmentId, $wardId,
        $data['bed_no'] ?? '',
        $data['admission_date'] ?? DateHelper::now(),
        $data['admission_diagnosis'] ?? '',
        $data['admission_icd_code'] ?? '',
        $nursingLevel, $feeType, $allergyHistory, $vteCaprini, $vteBleeding, $isCritical,
        (float)($data['prepay_fee'] ?? 0),
        (float)($data['overdraft_limit'] ?? 1000),
        'active',
        DateHelper::now()
    ]);
    $newId = (int)$pdo->lastInsertId();

    if (!empty($data['bed_no'])) {
        $bedUpdate = $pdo->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE ward_id = ? AND bed_no = ?");
        $bedUpdate->execute([$newId, $wardId, $data['bed_no']]);
    }

    /* 入院自带诊断 = 门(急)诊诊断: 归入诊断模块 (供优先级联动与病历引用) */
    $seedName = trim((string)($data['admission_diagnosis'] ?? ''));
    if ($seedName !== '') {
        try {
            emr_diag_seed_outpatient($newId, $admissionNo, $seedName, (string)($data['admission_icd_code'] ?? ''));
        } catch (Exception $e) { /* 诊断联动失败不阻断入院办理 */ }
    }

    Response::success(['id' => $newId, 'patient_no' => $patientNo, 'admission_no' => $admissionNo]);
}
