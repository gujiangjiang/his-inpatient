<?php
// api/modules/nurse/admission/admit.php - 护士站入科登记 (空闲床位收住新患者)
// 护士仅可在本人病区登记; 权限: admin / nurse
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$name = trim((string)Validator::get('name', ''));
$gender = Validator::get('gender', '');
$birthDate = Validator::get('birth_date', '');
$idCard = trim((string)Validator::get('id_card', ''));
$diagnosis = trim((string)Validator::get('admission_diagnosis', ''));
$icdCode = trim((string)Validator::get('admission_icd_code', ''));
$bedId = (int)Validator::get('bed_id', 0);

if ($name === '' || !$gender || !$birthDate || $diagnosis === '') {
    Response::error('请填写姓名/性别/出生日期/诊断');
}
if (!$bedId) Response::error('请选择目标床位');

$pdo = DB::getPDO();
$bed = DB::selectOne("SELECT * FROM beds WHERE id = ?", [$bedId]);
if (!$bed) Response::error('床位不存在');
if ($bed['status'] === 'occupied' && !empty($bed['patient_id'])) Response::error('床位已被占用');
if ($user['role'] === 'nurse' && (int)$bed['ward_id'] !== (int)$user['ward_id']) {
    Response::error('无权在该病区登记', 403);
}
$ward = DB::selectOne("SELECT * FROM wards WHERE id = ?", [$bed['ward_id']]);
if (!$ward) Response::error('病区不存在');

// 患者号/住院号生成
$noTail = seq_serial_tail('patients', 'patient_no', 'P');
$patientNo = 'P' . date('Ymd') . $noTail;
$admissionNo = 'A' . date('Ymd') . $noTail;

$pdo->beginTransaction();
try {
    $now = DateHelper::now();
    $stmt = $pdo->prepare("INSERT INTO patients (patient_no, name, gender, birth_date, id_card, admission_no,
        department_id, ward_id, bed_no, admission_date, admission_diagnosis, admission_icd_code,
        nursing_level, fee_type, allergy_history, is_critical, prepay_fee, overdraft_limit, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patientNo, $name, $gender, $birthDate, $idCard, $admissionNo,
        (int)($ward['department_id'] ?? 0), (int)$bed['ward_id'], $bed['bed_no'], $now,
        $diagnosis, $icdCode, '3', '', '', 0, 0.0, 1000.0, 'active', $now
    ]);
    $newId = (int)$pdo->lastInsertId();

    $bedUpdate = $pdo->prepare("UPDATE beds SET status = 'occupied', patient_id = ?
        WHERE id = ? AND status = 'free'");
    $bedAff = $bedUpdate->execute([$newId, $bedId]) ? $bedUpdate->rowCount() : 0;
    if ($bedAff !== 1) {
        $pdo->rollback();
        Response::error('床位已被占用，请更换床位');
    }

    // 门(急)诊诊断种子
    try {
        emr_diag_seed_outpatient($newId, $admissionNo, $diagnosis, $icdCode);
    } catch (Exception $e) { /* 不阻断 */ }

    $pdo->commit();
    Response::success(['id' => $newId, 'admission_no' => $admissionNo, 'bed_no' => $bed['bed_no']], '已收住 ' . $bed['bed_no'] . ' 床');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    Response::error('入科登记失败：' . $e->getMessage());
}
