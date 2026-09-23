<?php
// api/modules/case_front_page/autofill.php
Auth::requireRole(['admin', 'doctor']);
$admissionNo = Validator::get('admission_no');
if (!$admissionNo) Response::error('请输入住院号');

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT p.*, d.name as department_name, w.name as ward_name, d.code as dept_code, w.code as ward_code
    FROM patients p
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN wards w ON p.ward_id = w.id
    WHERE p.admission_no = ?", [$admissionNo]);

if (!$patient) Response::error('患者不存在');

// 获取诊断
$diag = DB::selectOne("SELECT diagnosis, icd_code FROM emr_records WHERE admission_no = ? AND record_type = 'admission' ORDER BY id DESC LIMIT 1", [$admissionNo]);

// 获取费用（模拟）
$fee = ['total' => 0, 'items' => []];

Response::success([
    'patient' => $patient,
    'diagnosis' => $diag ? $diag['diagnosis'] : $patient['admission_diagnosis'],
    'icd_code' => $diag ? $diag['icd_code'] : $patient['admission_icd_code'],
    'fee' => $fee,
    'surgeries' => []
]);
