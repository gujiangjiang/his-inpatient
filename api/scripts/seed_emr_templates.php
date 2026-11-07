<?php
// api/scripts/seed_emr_templates.php - 结构化病历种子数据 (幂等)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/time_helper.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
$pdo->beginTransaction();

// === 病历分类 ===
$categories = [
    ['入院记录', 'admission', 1],
    ['病程记录', 'progress', 2],
    ['手术相关', 'surgery', 3],
    ['沟通记录', 'communication', 4],
    ['出院记录', 'discharge', 5],
];
foreach ($categories as $c) {
    $check = $pdo->prepare("SELECT id FROM emr_categories WHERE code = ?");
    $check->execute([$c[1]]);
    if ($check->fetch()) continue;
    $pdo->prepare("INSERT INTO emr_categories (name, code, sort_order, is_active, created_at) VALUES (?,?,?,1,?)")
        ->execute([$c[0], $c[1], $c[2], DateHelper::now()]);
}

// === 入院记录字处理模板 (word 模式: 类 Word 整页排版, 固定标签/填写槽) ===
$hospital = '';
try {
    $h = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'hospital_name'");
    if ($h) $hospital = (string)$h['config_value'];
} catch (Exception $e) { /* 忽略 */ }
if ($hospital === '') $hospital = '医院名称';

$P = function (array $runs, array $pPr = []) { return ['t' => 'p', 'pPr' => $pPr, 'runs' => $runs]; };
$fx = function ($key, $text) { return ['t' => 'fx', 'key' => $key, 'text' => $text]; };
$slot = function ($key, $ph) { return ['t' => 'slot', 'key' => $key, 'ph' => $ph]; };
$dyn = function ($key, $text = '') { return ['t' => 'dyn', 'key' => $key, 'text' => $text]; };
$secHead = function ($text) { return ['t' => 'p', 'pPr' => ['align' => 'center', 'borders' => ['top' => true]], 'rPr' => ['b' => true, 'sz' => 13], 'runs' => [['t' => 'text', 'text' => $text]]]; };
$sp = ['t' => 'text', 'text' => "\u{3000}\u{3000}"];

$wordBody = [
    $P([$fx('chief_complaint', '主诉：'), $slot('chief_complaint_val', '患者主诉症状及持续时间')]),
    $P([$fx('present_illness', '现病史：'), $slot('present_illness_val', '请输入现病史')]),
    $P([$fx('past_history', '既往史：'), $slot('past_history_val', '请输入既往史')]),
    $P([$fx('personal_history', '个人史：'), $slot('personal_history_val', '请输入个人史')]),
    $P([$fx('marriage_history', '婚育史：'), $slot('marriage_history_val', '请输入婚育史')]),
    $P([$fx('family_history', '家族史：'), $slot('family_history_val', '请输入家族史')]),
    $secHead('体格检查'),
    $P([$fx('vital_temp', '体温：'), $slot('vital_temp_val', '℃'), $sp, $fx('vital_hr', '心率：'), $slot('vital_hr_val', '次/分'), $sp, $fx('vital_bp', '血压：'), $slot('vital_bp_val', 'mmHg')]),
    $P([$fx('vital_height', '身高：'), $slot('vital_height_val', 'cm'), $sp, $fx('vital_weight', '体重：'), $slot('vital_weight_val', 'kg'), $sp, $fx('vital_bmi', 'BMI：'), $dyn('vital_bmi_value', '')]),
    $P([$slot('physical_exam_val', '请输入体格检查')]),
    $secHead('专科检查'),
    $P([$slot('special_exam_val', '请输入专科检查')]),
    $secHead('辅助检查'),
    $P([$slot('aux_exam_val', '请输入辅助检查')]),
    $P([$fx('initial_diagnosis', '初步诊断：'), $dyn('initial_diagnosis_value', '')], ['align' => 'right']),
    $P([$fx('sign_line', '医师签名：'), $slot('sign_line_val', '（上级签名，没有则不显示）/（下级签名）')], ['align' => 'right']),
];

$wordSchema = [
    'mode' => 'word',
    'title' => '入院记录',
    'page' => ['paper' => 'A4', 'mt' => 40, 'mb' => 36, 'ml' => 36, 'mr' => 36, 'header_h' => 16, 'footer_h' => 40,
        'font' => '仿宋_GB2312, FangSong, SimSun, serif', 'sz' => 12, 'line' => 1.5],
    'header' => [
        'hospital' => $hospital,
        'doc_name' => '入院记录',
        'info_fields' => [
            ['姓名', 'name'], ['性别', 'gender'], ['科别', 'department'], ['病区', 'ward_name'],
            ['年龄', 'age'], ['床号', 'bed_no'], ['住院号', 'admission_no'],
        ],
    ],
    'body' => $wordBody,
];

$admissionCategory = $pdo->query("SELECT id FROM emr_categories WHERE code = 'admission'")->fetch();
$check = $pdo->query("SELECT id FROM emr_templates WHERE name = '常规入院记录'")->fetch();
if (!$check) {
    $pdo->prepare("INSERT INTO emr_templates (name, record_type, schema_json, category_id, usage_count, created_at) VALUES ('常规入院记录', 'admission', ?, ?, 0, ?)")
        ->execute([json_encode($wordSchema, JSON_UNESCAPED_UNICODE), $admissionCategory['id'], DateHelper::now()]);
} else {
    $pdo->prepare("UPDATE emr_templates SET schema_json = ?, category_id = ? WHERE id = ?")
        ->execute([json_encode($wordSchema, JSON_UNESCAPED_UNICODE), $admissionCategory['id'], $check['id']]);
}

// 早期版本创建的同义模板 '入院记录(字处理)': 无文书引用则删除, 有引用则同步为新设计
$checkWord = $pdo->query("SELECT id FROM emr_templates WHERE name = '入院记录(字处理)'")->fetch();
if ($checkWord) {
    $ref = $pdo->prepare("SELECT COUNT(*) AS n FROM emr_documents WHERE template_id = ?");
    $ref->execute([$checkWord['id']]);
    $refRow = $ref->fetch();
    if ((int)$refRow['n'] === 0) {
        $pdo->prepare("DELETE FROM emr_templates WHERE id = ?")->execute([$checkWord['id']]);
    } else {
        $pdo->prepare("UPDATE emr_templates SET schema_json = ?, category_id = ? WHERE id = ?")
            ->execute([json_encode($wordSchema, JSON_UNESCAPED_UNICODE), $admissionCategory['id'], $checkWord['id']]);
    }
}

// === 医生科室授权 ===
$doctor1 = DB::selectOne("SELECT id, department_id FROM users WHERE username = 'doctor1'");
$doctor2 = DB::selectOne("SELECT id, department_id FROM users WHERE username = 'doctor2'");
foreach ([$doctor1, $doctor2] as $d) {
    if (!$d || !$d['department_id']) continue;
    $chk = $pdo->prepare("SELECT id FROM doctor_department_permissions WHERE doctor_id = ? AND department_id = ?");
    $chk->execute([$d['id'], $d['department_id']]);
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO doctor_department_permissions (doctor_id, department_id, created_at) VALUES (?,?,?)")
            ->execute([$d['id'], $d['department_id'], DateHelper::now()]);
    }
}

// === 患者指派 (demo 患者 → doctor1/doctor2) ===
$patients = DB::select("SELECT id, admission_no, department_id FROM patients ORDER BY id");
foreach ($patients as $idx => $p) {
    $chk = $pdo->prepare("SELECT id FROM patient_assignments WHERE patient_id = ?");
    $chk->execute([$p['id']]);
    if ($chk->fetch()) continue;
    $att = ($idx % 2 === 0) ? $doctor1['id'] : $doctor2['id'];
    $senior = ($idx % 2 === 0) ? $doctor2['id'] : $doctor1['id'];
    $pdo->prepare("INSERT INTO patient_assignments (patient_id, admission_no, attending_doctor_id, senior_doctor_id, department_id, created_at) VALUES (?,?,?,?,?,?)")
        ->execute([$p['id'], $p['admission_no'], $att, $senior, $p['department_id'], DateHelper::now()]);
}

// === 待入科患者 (已分配床位但未指派医师, 供"添加"Tab测试) ===
$chk = $pdo->prepare("SELECT id FROM patients WHERE admission_no = ?");
$chk->execute(['A202609230099']);
if (!$chk->fetch()) {
    $pdo->prepare("INSERT INTO patients (patient_no, name, gender, birth_date, id_card, admission_no, department_id, ward_id, bed_no, admission_date, admission_diagnosis, admission_icd_code, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            'P202609230099', '欧阳新', 'male', '1976-03-08', '110101197603081234',
            'A202609230099', 1, 1, '5', DateHelper::now(), '待入科测试患者-急性阑尾炎待排', 'K35.9',
            'active', DateHelper::now()
        ]);
}

$pdo->commit();
echo "结构化病历种子数据导入完成。\n";
echo "分类: " . count($categories) . " 个, 入院记录字处理模板已就绪, 指派/授权已生成。\n";