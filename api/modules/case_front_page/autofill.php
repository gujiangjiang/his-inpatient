<?php
// api/modules/case_front_page/autofill.php - 病案首页自动填充 (患者/诊断/手术/医师)
Auth::requireRole(['admin', 'doctor']);
$admissionNo = Validator::get('admission_no');
if (!$admissionNo) Response::error('请输入住院号');

$patient = DB::selectOne("SELECT p.*, d.name as department_name, w.name as ward_name
    FROM patients p
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN wards w ON p.ward_id = w.id
    WHERE p.admission_no = ?", [$admissionNo]);
if (!$patient) Response::error('患者不存在');

// 三级医师
$assignment = DB::selectOne("SELECT a.name as attending_name, s.name as senior_name, d.name as director_name
    FROM patient_assignments pa
    LEFT JOIN users a ON pa.attending_doctor_id = a.id
    LEFT JOIN users s ON pa.senior_doctor_id = s.id
    LEFT JOIN users d ON pa.director_doctor_id = d.id
    WHERE pa.admission_no = ?", [$admissionNo]);

// 诊断 (门诊/入院/出院)
$diags = DB::select("SELECT category, icd_code, name FROM patient_diagnoses WHERE admission_no = ? ORDER BY id", [$admissionNo]);
$pick = function ($cat) use ($diags) {
    foreach ($diags as $d) {
        if ($d['category'] === $cat) return $d;
    }
    return null;
};
$emergency = $pick('emergency') ?: $pick('initial');
$admissionDiag = $pick('admission');
$dischargeRows = array_values(array_filter($diags, function ($d) {
    return $d['category'] === 'discharge';
}));
if (!$dischargeRows && $admissionDiag) $dischargeRows = [$admissionDiag];

// 手术记录 (映射手术表行)
$surgeryRows = [];
foreach (DB::select("SELECT * FROM surgeries WHERE admission_no = ? ORDER BY surgery_date", [$admissionNo]) as $s) {
    $surgeryRows[] = [
        'code' => '', 'date' => $s['surgery_date'] ? substr((string)$s['surgery_date'], 0, 10) : '',
        'level' => '', 'category' => '', 'name' => $s['surgery_name'],
        'surgeon' => $s['surgeon'], 'a1' => '', 'a2' => '',
        'incision' => '', 'heal' => $s['incision_healing'] ? '1' : '', 'anesthesia' => $s['anesthesia_type'],
        'anes_doc' => '',
    ];
}

// 费用 (默认 0, 由页面录入)
$feeKeys = [];
for ($i = 1; $i <= 24; $i++) $feeKeys['fee' . $i] = 0;
$fee = array_merge(['fee_total' => 0, 'fee_self' => 0], $feeKeys);

Response::success([
    'patient' => $patient,
    'assignment' => $assignment,
    'diagnoses' => [
        'emergency' => $emergency,
        'admission' => $admissionDiag,
        'discharge_list' => $dischargeRows,
    ],
    'surgeries' => $surgeryRows,
    'fee' => $fee,
]);
