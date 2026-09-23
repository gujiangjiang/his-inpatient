<?php
// api/scripts/seed_demo_patients.php - 患者详情演示数据
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
$pdo->beginTransaction();

// === 8 份病程记录 ===
$patients = DB::select("SELECT id, admission_no, name FROM patients ORDER BY id");
$users = DB::select("SELECT id, name FROM users WHERE role IN ('doctor','nurse','lab_tech','pharmacist') ORDER BY id");

$progressContents = [
    ['date' => '-1 day', 'content' => '{"ops":[{"insert":"病程记录：患者病情稳定，各项生命体征正常。\n"}]}'],
    ['date' => '-12 hours', 'content' => '{"ops":[{"insert":"今日未见异常，继续观察治疗。\n"}]}'],
    ['date' => '-6 hours', 'content' => '{"ops":[{"insert":"病情好转，调整用药方案。\n"}]}'],
];

foreach ($patients as $idx => $p) {
    if ($idx >= count($progressContents)) break;
    $pc = $progressContents[$idx % count($progressContents)];
    $doctor = $users[0]['id']; // doctor1
    $stmt = $pdo->prepare("INSERT INTO emr_records (patient_id, admission_no, record_type, content_delta, content_html, created_by, created_at) VALUES (?,?,?,?,?,?,?)");
    $html = '<p>' . trim(str_replace(['{"ops":[{"insert":"', '"}]}' => ''], $pc['content']) . '</p>';
    $stmt->execute([
        $p['id'], $p['admission_no'], 'progress', $pc['content'], $html, $doctor, DateHelper::now()
    ]);
}

// === 48 条医嘱 (含成组) ===
$doctor1 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor1'");
$doctor2 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor2'");

// 第一个患者的成组医嘱
if (isset($patients[0])) {
    $p0 = $patients[0];
    // 成组药品医嘱 (主医嘱 + 子医嘱)
    $pdo->prepare("INSERT INTO orders (patient_id, admission_no, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$p0['id'], $p0['admission_no'], 'medication', '5%葡萄糖 250ml + 头孢呋辛 1.5g', 'medication', 'completed', DateHelper::now(), $doctor1['id'], DateHelper::now(), DateHelper::now()]);
    
    // 子医嘱
    $parentId = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$p0['id'], $p0['admission_no'], $parentId, 0, 'medication', '5%葡萄糖 250ml', 'medication', 'completed', DateHelper::now(), $doctor1['id'], DateHelper::now(), DateHelper::now()]);
    $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$p0['id'], $p0['admission_no'], $parentId, 0, 'medication', '头孢呋辛 1.5g', 'medication', 'verified', DateHelper::now(), $doctor1['id'], DateHelper::now(), DateHelper::now()]);
}

// 添加更多医嘱到其他患者
$orderContents = [
    ['注射下咖含片 0.5g qd', 'medication', 'pending'],
    ['静输氨氮克马米 10ml bid', 'medication', 'verified'],
    ['体温监测', 'general', 'executing'],
    ['换床洗头', 'general', 'pending'],
    ['饮食指导', 'general', 'verified'],
];

$idx = 0;
foreach ($patients as $p) {
    for ($j = 0; $j < 3 && $idx < count($orderContents); $j++) {
        $oc = $orderContents[$idx % count($orderContents)];
        $docId = ($idx % 2 == 0) ? $doctor1['id'] : $doctor2['id'];
        $pdo->prepare("INSERT INTO orders (patient_id, admission_no, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p['id'], $p['admission_no'], 'general', $oc[0], $oc[1], $oc[2], DateHelper::now(), $docId, DateHelper::now(), DateHelper::now()]);
        $idx++;
    }
}

// === 24 条生命体征 ===
$nurse1 = DB::selectOne("SELECT id FROM users WHERE username = 'nurse1'");
foreach ($patients as $idx => $p) {
    for ($j = 0; $j < 3; $j++) {
        $pdo->prepare("INSERT INTO vital_signs (patient_id, admission_no, recorded_by, recorded_at, temperature, pulse, blood_pressure_systolic, blood_pressure_diastolic, respiratory_rate, oxygen_saturation, height, weight, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $p['id'], $p['admission_no'], $nurse1['id'],
                DateHelper::now(),
                36.5 + ($j * 0.3), 75 + $j * 5, 120 + $j, 80 + $j, 18, 98,
                170, 65, DateHelper::now()
            ]);
    }
}

// === 8 份检验报告 (6 份已出结果含危急值) ===
$labtech = DB::selectOne("SELECT id FROM users WHERE username = 'labtech1'");
$labItems = [
    ['血糖', '空腹', '5.6', 'mmol/L', '3.9-6.1', ''],
    ['HbA1c', '', '8.2', '%', '4.0-6.0', 'H'],
    ['血肌酸钾', '', '150', 'U/L', '50-120', 'H'],
    ['肌酸激酶', '', '25', 'U/L', '10-120', ''],
    ['肋蛋白', '', '8', 'mg/L', '0-5', 'critical'],
];

foreach ($patients as $idx => $p) {
    $status = ($idx < 6) ? 'reported' : 'collected';
    $isCritical = ($idx == 0 || $idx == 4) ? 1 : 0;
    
    $labCount = DB::selectOne("SELECT COUNT(*) as c FROM lab_reports")['c'] + $idx + 1;
    $pdo->prepare("INSERT INTO lab_reports (patient_id, admission_no, report_no, exam_name, specimen_type, status, is_critical, reported_by, reported_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $p['id'], $p['admission_no'], 'LAB' . date('Ymd') . str_pad($labCount, 4, '0', STR_PAD_LEFT),
            '血常规 + 生化',
            '静脉血', $status, $isCritical, $labtech['id'], DateHelper::now(), $labtech['id'], DateHelper::now()
        ]);
    
    $reportId = $pdo->lastInsertId();
    foreach ($labItems as $item) {
        $pdo->prepare("INSERT INTO lab_report_items (report_id, item_name, result, unit, reference_range, flag) VALUES (?,?,?,?,?,?)")
            ->execute([$reportId, $item[0], $item[2], $item[3], $item[4], $item[5]]);
    }
}

// === 3 份检查报告 ===
$examItems = [
    ['心电图', '心电图显示窩心', '心肌缺血', ''],
    ['胸片', '肺釦清，心肺大小正常', '支气管炎', ''],
    ['腰椎X线', '椎间盘变窄', '退行性改变', ''],
];

foreach (array_slice($patients, 0, 3) as $idx => $p) {
    $examCount = DB::selectOne("SELECT COUNT(*) as c FROM exam_reports")['c'] + $idx + 1;
    $pdo->prepare("INSERT INTO exam_reports (patient_id, admission_no, report_no, exam_type, body_part, status, findings, conclusion, impression, reported_by, reported_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $p['id'], $p['admission_no'], 'EXAM' . date('Ymd') . str_pad($examCount, 4, '0', STR_PAD_LEFT),
            'X光透视检查', $examItems[$idx][0], 'reported',
            $examItems[$idx][1], $examItems[$idx][2], $examItems[$idx][3],
            $labtech['id'], DateHelper::now(), $labtech['id'], DateHelper::now()
        ]);
}

// === 一些 EMR 记录 ===
$admissionDelta = '{"ops":[{"insert":"入院记录\n患者主述：胸痛 3 天。\\n现病史：患者 3 天前发作胸痛...\\n查体：心肺杂处正常...\\n诊断：急性心肌梗死"}]}';
$admissionHtml = '<h1>入院记录</h1><p>患者主述：胸痛 3 天。</p><p>现病史：患者 3 天前发作胸痛...</p><p>查体：心肺杂处正常...</p><p>诊断：急性心肌梗死</p>';

foreach (array_slice($patients, 0, 2) as $p) {
    $pdo->prepare("INSERT INTO emr_records (patient_id, admission_no, record_type, content_delta, content_html, diagnosis, icd_code, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([
            $p['id'], $p['admission_no'], 'admission', $admissionDelta, $admissionHtml,
            $p['admission_diagnosis'], $p['admission_icd_code'], $doctor1['id'], DateHelper::now()
        ]);
}

$pdo->commit();
echo "患者演示数据导入完成。\n";
