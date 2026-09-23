<?php
// api/scripts/seed_demo_patients.php - 患者详情演示数据 (幂等可重复执行)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
$pdo->beginTransaction();

$patients = DB::select("SELECT id, admission_no, name, admission_diagnosis, admission_icd_code FROM patients ORDER BY id");
$doctor1 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor1'");
$doctor2 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor2'");
$nurse1 = DB::selectOne("SELECT id FROM users WHERE username = 'nurse1'");
$labtech = DB::selectOne("SELECT id FROM users WHERE username = 'labtech1'");

// === 8 份病程记录 (幂等) ===
$progressContents = [
    '{"ops":[{"insert":"病程记录：患者病情稳定，各项生命体征正常。\n"}]}',
    '{"ops":[{"insert":"今日未见异常，继续观察治疗。\n"}]}',
    '{"ops":[{"insert":"病情好转，调整用药方案。\n"}]}',
];

foreach (array_slice($patients, 0, count($progressContents)) as $idx => $p) {
    $existing = DB::selectOne("SELECT id FROM emr_records WHERE patient_id = ? AND record_type = 'progress'", [$p['id']]);
    if ($existing) continue;
    $pc = $progressContents[$idx];
    $html = '<p>' . str_replace(['{"ops":[{"insert":"', '"}]}'], ['', ''], $pc) . '</p>';
    $pdo->prepare("INSERT INTO emr_records (patient_id, admission_no, record_type, content_delta, content_html, created_by, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$p['id'], $p['admission_no'], 'progress', $pc, $html, $doctor1['id'], DateHelper::now()]);
}

// === 医嘱 (幂等) ===
if (DB::selectOne("SELECT COUNT(*) as c FROM orders")['c'] === 0) {
    // 第一个患者成组药品医嘱 (主医嘱 + 2 子医嘱)
    if (isset($patients[0])) {
        $p0 = $patients[0];
        $pdo->prepare("INSERT INTO orders (patient_id, admission_no, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p0['id'], $p0['admission_no'], 'medication', '5%葡萄糖 250ml + 头孢呋辛 1.5g', 'medication', 'verified', DateHelper::now(), $doctor1['id'], DateHelper::now(), DateHelper::now()]);
        $parentId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p0['id'], $p0['admission_no'], $parentId, 0, 'medication', '5%葡萄糖 250ml', 'medication', 'verified', DateHelper::now(), $doctor1['id'], DateHelper::now(), DateHelper::now()]);
        $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p0['id'], $p0['admission_no'], $parentId, 0, 'medication', '头孢呋辛 1.5g', 'medication', 'verified', DateHelper::now(), $doctor1['id'], DateHelper::now(), DateHelper::now()]);
    }

    // 其他患者医嘱
    $orderContents = [
        ['阿司匹林 100mg qd', 'medication', 'pending'],
        ['体温监测 bid', 'general', 'verified'],
        ['饮食指导', 'general', 'pending'],
        ['换药处理', 'general', 'verified'],
        ['血常规复查', 'general', 'executing'],
        ['胸片检查', 'general', 'executing'],
    ];
    foreach ($patients as $idx => $p) {
        $oc = $orderContents[$idx % count($orderContents)];
        $docId = ($idx % 2 == 0) ? $doctor1['id'] : $doctor2['id'];
        $pdo->prepare("INSERT INTO orders (patient_id, admission_no, order_type, content, category, status, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$p['id'], $p['admission_no'], 'general', $oc[0], $oc[1], $oc[2], DateHelper::now(), $docId, DateHelper::now(), DateHelper::now()]);
    }
}

// === 24 条生命体征 (幂等) ===
if (DB::selectOne("SELECT COUNT(*) as c FROM vital_signs")['c'] === 0) {
    foreach ($patients as $p) {
        for ($j = 0; $j < 3; $j++) {
            $pdo->prepare("INSERT INTO vital_signs (patient_id, admission_no, recorded_by, recorded_at, temperature, pulse, blood_pressure_systolic, blood_pressure_diastolic, respiratory_rate, oxygen_saturation, height, weight, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $p['id'], $p['admission_no'], $nurse1['id'], DateHelper::now(),
                    36.5 + ($j * 0.3), 75 + $j * 5, 120 + $j, 80 + $j, 18, 98, 170, 65, DateHelper::now()
                ]);
        }
    }
}

// === 8 份检验报告 (幂等) ===
$labItems = [
    ['血糖', '5.6', 'mmol/L', '3.9-6.1', ''],
    ['HbA1c', '8.2', '%', '4.0-6.0', 'H'],
    ['血肌酸钾', '150', 'U/L', '50-120', 'H'],
    ['肌酸激酶', '25', 'U/L', '10-120', ''],
    ['肋蛋白', '8', 'mg/L', '0-5', 'critical'],
];

foreach ($patients as $idx => $p) {
    $reportNo = 'LAB' . date('Ymd') . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);
    if (DB::selectOne("SELECT id FROM lab_reports WHERE report_no = ?", [$reportNo])) continue;

    $status = ($idx < 6) ? 'reported' : 'collected';
    $isCritical = ($idx == 0 || $idx == 4) ? 1 : 0;

    $pdo->prepare("INSERT INTO lab_reports (patient_id, admission_no, report_no, exam_name, specimen_type, status, is_critical, reported_by, reported_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $p['id'], $p['admission_no'], $reportNo, '血常规 + 生化', '静脉血',
            $status, $isCritical, $labtech['id'], DateHelper::now(), $labtech['id'], DateHelper::now()
        ]);

    $reportId = $pdo->lastInsertId();
    foreach ($labItems as $item) {
        $pdo->prepare("INSERT INTO lab_report_items (report_id, item_name, result, unit, reference_range, flag) VALUES (?,?,?,?,?,?)")
            ->execute([$reportId, $item[0], $item[1], $item[2], $item[3], $item[4]]);
    }
}

// === 3 份检查报告 (幂等) ===
$examItems = [
    ['心电图', '心电图显示窦性心律', '心肌缺血', '建议复查'],
    ['胸片', '肺野清晰，心肺大小正常', '支气管炎', ''],
    ['腰椎X线', '椎间盘变窄', '退行性改变', ''],
];

foreach (array_slice($patients, 0, count($examItems)) as $idx => $p) {
    $reportNo = 'EXAM' . date('Ymd') . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);
    if (DB::selectOne("SELECT id FROM exam_reports WHERE report_no = ?", [$reportNo])) continue;
    $ei = $examItems[$idx];
    $pdo->prepare("INSERT INTO exam_reports (patient_id, admission_no, report_no, exam_type, body_part, status, findings, conclusion, impression, reported_by, reported_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $p['id'], $p['admission_no'], $reportNo, 'X光透视检查', $ei[0], 'reported',
            $ei[1], $ei[2], $ei[3], $labtech['id'], DateHelper::now(), $labtech['id'], DateHelper::now()
        ]);
}

// === 入院记录 EMR (幂等) ===
$admissionDelta = '{"ops":[{"insert":"入院记录\n患者主述：胸痛 3 天。\n现病史：患者 3 天前发作胸痛...\n查体：心肺杂处正常...\n诊断：急性心肌梗死"}]}';
$admissionHtml = '<h1>入院记录</h1><p>患者主述：胸痛 3 天。</p><p>现病史：患者 3 天前发作胸痛...</p><p>查体：心肺杂处正常...</p><p>诊断：急性心肌梗死</p>';

foreach (array_slice($patients, 0, 2) as $p) {
    $existing = DB::selectOne("SELECT id FROM emr_records WHERE patient_id = ? AND record_type = 'admission'", [$p['id']]);
    if ($existing) continue;
    $pdo->prepare("INSERT INTO emr_records (patient_id, admission_no, record_type, content_delta, content_html, diagnosis, icd_code, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([
            $p['id'], $p['admission_no'], 'admission', $admissionDelta, $admissionHtml,
            $p['admission_diagnosis'], $p['admission_icd_code'], $doctor1['id'], DateHelper::now()
        ]);
}

$pdo->commit();
echo "患者演示数据导入完成。\n";