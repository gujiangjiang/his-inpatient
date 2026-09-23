<?php
// api/scripts/seed_emr_templates.php - 结构化病历种子数据 (幂等)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
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

// === 入院记录结构化模板 ===
$admissionSchema = [
    'meta' => [
        'title' => '入院记录',
        'category' => 'admission',
        'layout' => [
            'font_family' => '仿宋_GB2312, SimSun, serif',
            'font_size' => '14px',
            'line_height' => '1.5',
            'indent' => '2em',
            'page' => 'A4'
        ]
    ],
    'sections' => [
        ['key' => 'chief_complaint', 'title' => '主诉', 'type' => 'textarea', 'required' => true, 'slot' => '患者主诉症状及持续时间'],
        ['key' => 'present_illness', 'title' => '现病史', 'type' => 'textarea', 'required' => true],
        ['key' => 'past_history', 'title' => '既往史', 'type' => 'textarea', 'required' => false],
        ['key' => 'personal_history', 'title' => '个人史', 'type' => 'textarea', 'required' => false],
        ['key' => 'marriage_history', 'title' => '婚育史', 'type' => 'textarea', 'required' => false],
        ['key' => 'family_history', 'title' => '家族史', 'type' => 'textarea', 'required' => false],
        ['key' => 'physical_exam', 'title' => '体格检查', 'type' => 'textarea', 'required' => true],
        ['key' => 'aux_exam', 'title' => '辅助检查', 'type' => 'textarea', 'required' => false],
        ['key' => 'initial_diagnosis', 'title' => '初步诊断', 'type' => 'textarea', 'required' => true],
        ['key' => 'treatment_plan', 'title' => '治疗意见', 'type' => 'textarea', 'required' => false],
    ]
];
$admissionCategory = $pdo->query("SELECT id FROM emr_categories WHERE code = 'admission'")->fetch();

$check = $pdo->query("SELECT id FROM emr_templates WHERE name = '常规入院记录'")->fetch();
if (!$check) {
    $pdo->prepare("INSERT INTO emr_templates (name, record_type, schema_json, category_id, usage_count, created_at) VALUES ('常规入院记录', 'admission', ?, ?, 0, ?)")
        ->execute([json_encode($admissionSchema, JSON_UNESCAPED_UNICODE), $admissionCategory['id'], DateHelper::now()]);
} else {
    $pdo->prepare("UPDATE emr_templates SET schema_json = ?, category_id = ? WHERE id = ?")
        ->execute([json_encode($admissionSchema, JSON_UNESCAPED_UNICODE), $admissionCategory['id'], $check['id']]);
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
echo "分类: " . count($categories) . " 个, 入院记录模板已就绪, 指派/授权已生成。\n";