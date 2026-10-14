<?php
// api/modules/case_front_page/autofill.php - 病案首页自动填充 (患者/出入院/诊断/手术/医师/费用)
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

// 诊断 (门诊/入院/出院/病理/损伤中毒)
$diags = DB::select("SELECT category, icd_code, name, created_at FROM patient_diagnoses WHERE admission_no = ? ORDER BY id", [$admissionNo]);
$pick = function ($cat) use ($diags) {
    foreach ($diags as $d) {
        if ($d['category'] === $cat) return $d;
    }
    return null;
};
$emergency = $pick('emergency') ?: $pick('initial');
$admissionDiag = $pick('admission');
$pathology = $pick('pathology');
$external = $pick('external');
$dischargeRows = array_values(array_filter($diags, function ($d) {
    return $d['category'] === 'discharge';
}));
if (!$dischargeRows && $admissionDiag) $dischargeRows = [$admissionDiag];

// 诊断符合情况 (九项 → 首页 1~5)
$matchMap = [];
foreach (DB::select("SELECT mkey, mvalue FROM patient_diag_match WHERE patient_id = ?", [$patient['id']]) as $m) {
    $matchMap[$m['mkey']] = (string)$m['mvalue'];
}

// 出院信息 (出院记录最新一条)
$discharge = DB::selectOne("SELECT * FROM discharge_records WHERE admission_no = ? ORDER BY id DESC LIMIT 1", [$admissionNo]);
$dischargeTime = $discharge ? (string)($discharge['discharged_at'] ?? '') : '';

// 住院天数 (入院 → 出院/当前)
$stayDays = 1;
if (!empty($patient['admission_date'])) {
    $st = strtotime((string)$patient['admission_date']);
    $en = $dischargeTime ? strtotime($dischargeTime) : time();
    if ($st && $en) $stayDays = max(1, (int)ceil(($en - $st) / 86400));
}

// 年龄 (岁/月/天)
$age = ['y' => '', 'm' => '', 'd' => ''];
if (!empty($patient['birth_date'])) {
    $b = strtotime((string)$patient['birth_date']);
    if ($b) {
        $now = time();
        $days = (int)floor(($now - $b) / 86400);
        if ($days >= 365) {
            $age['y'] = (int)floor($days / 365.25);
        } elseif ($days > 28) {
            $age['m'] = max(1, (int)floor($days / 30.44));
        } else {
            $age['d'] = max(0, $days);
        }
    }
}

// 第几次住院 (按身份证计次)
$admitTimes = 1;
if (!empty($patient['id_card'])) {
    $row = DB::selectOne("SELECT COUNT(*) as c FROM patients WHERE id_card = ? AND id <= ?", [$patient['id_card'], $patient['id']]);
    $admitTimes = $row ? max(1, (int)$row['c']) : 1;
}

// 医疗付费方式 (费别 → 国家标准代码)
$payMap = [
    '职工医保' => '1', '城镇居民医保' => '2', '居民医保' => '2', '新农合' => '3',
    '贫困救助' => '4', '商业保险' => '5', '商业医疗保险' => '5', '全公费' => '6',
    '自费' => '7', '全自费' => '7', '其他社会保险' => '8', '其他' => '9',
];
$feeType = (string)($patient['fee_type'] ?? '');
$payType = $payMap[$feeType] ?? (preg_match('/^\d$/', $feeType) ? $feeType : '');

// 机构信息
$conf = [];
foreach (DB::select("SELECT config_key, config_value FROM system_config WHERE config_key IN ('hospital_name', 'hospital_code')") as $c) {
    $conf[$c['config_key']] = $c['config_value'];
}

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

// 住院费用 (有效医嘱自动归集, 页面只读)
$fee = patient_fee_breakdown((int)$patient['id']);

Response::success([
    'patient' => $patient,
    'assignment' => $assignment,
    'discharge' => $discharge,
    'fee' => $fee,
    'auto' => [
        'pay_type' => $payType,
        'mr_no' => (string)($patient['patient_no'] ?? ''),
        'admit_times' => $admitTimes,
        'age_y' => $age['y'], 'age_m' => $age['m'], 'age_d' => $age['d'],
        'admit_time' => $patient['admission_date'] ?? '',
        'admit_dept' => $patient['department_name'] ?? '',
        'admit_ward' => trim(($patient['ward_name'] ?? '') . ($patient['bed_no'] ? ' ' . $patient['bed_no'] . '床' : '')),
        'transfer_dept' => '',
        'discharge_time' => $dischargeTime,
        'discharge_dept' => $dischargeTime ? (string)($patient['department_name'] ?? '') : '',
        'discharge_ward' => $dischargeTime ? trim(($patient['ward_name'] ?? '') . ($patient['bed_no'] ? ' ' . $patient['bed_no'] . '床' : '')) : '',
        'stay_days' => $stayDays,
        'allergy' => empty($patient['allergy_history']) ? '1' : '2',
        'allergy_drugs' => (string)($patient['allergy_history'] ?? ''),
        'diagnose_date' => ($dischargeRows ? substr((string)($dischargeRows[0]['created_at'] ?? ''), 0, 10) : ''),
        'injury_reason' => $external ? (string)$external['name'] : '',
        'injury_code' => $external ? (string)$external['icd_code'] : '',
        'pathology_diagnosis' => $pathology ? (string)$pathology['name'] : '',
        'pathology_code' => $pathology ? (string)$pathology['icd_code'] : '',
        'match1' => $matchMap['out_in'] ?? '',
        'match2' => $matchMap['adm_out'] ?? '',
        'match3' => $matchMap['pre_post'] ?? '',
        'match4' => $matchMap['clin_path'] ?? '',
        'match5' => $matchMap['rad_path'] ?? '',
        'hospital_name' => $conf['hospital_name'] ?? '',
        'hospital_code' => $conf['hospital_code'] ?? '',
    ],
    'diagnoses' => [
        'emergency' => $emergency,
        'admission' => $admissionDiag,
        'discharge_list' => $dischargeRows,
    ],
    'surgeries' => $surgeryRows,
]);
