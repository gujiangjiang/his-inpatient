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

// === 内置住院病历模板 (字处理 word 模式, 定义见 emr_template_defs.php) ===
$hospital = '';
try {
    $h = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'hospital_name'");
    if ($h) $hospital = (string)$h['config_value'];
} catch (Exception $e) { /* 忽略 */ }
if ($hospital === '') $hospital = '医院名称';

// 清理早期版本种子产生的测试模板: 先解除文书引用(文书自有守卫, 不依赖模板), 再删除
$legacyRows = DB::select("SELECT id, name FROM emr_templates WHERE name IN ('常规入院记录','入院记录(字处理)') OR name LIKE '%(字处理)%' OR name LIKE '%测试%'");
foreach ($legacyRows as $t) {
    $pdo->prepare("UPDATE emr_documents SET template_id = NULL WHERE template_id = ?")->execute([$t['id']]);
    $pdo->prepare("DELETE FROM emr_templates WHERE id = ?")->execute([$t['id']]);
}

$defsFn = require __DIR__ . '/emr_template_defs.php';
$defs = $defsFn($hospital);
foreach ($defs as $d) {
    list($name, $recordType, $catCode, $schema) = $d;
    $cat = DB::selectOne("SELECT id FROM emr_categories WHERE code = ?", [$catCode]);
    if (!$cat) continue;
    $json = json_encode($schema, JSON_UNESCAPED_UNICODE);
    $check = $pdo->prepare("SELECT id FROM emr_templates WHERE name = ?");
    $check->execute([$name]);
    $row = $check->fetch();
    if ($row) {
        $pdo->prepare("UPDATE emr_templates SET schema_json = ?, category_id = ?, record_type = ? WHERE id = ?")
            ->execute([$json, $cat['id'], $recordType, $row['id']]);
    } else {
        $pdo->prepare("INSERT INTO emr_templates (name, record_type, schema_json, category_id, usage_count, created_at) VALUES (?,?,?,?,0,?)")
            ->execute([$name, $recordType, $json, $cat['id'], DateHelper::now()]);
    }
}
if ($legacyRows) {
    echo "已清理测试模板: " . implode(', ', array_map(function ($x) { return $x['name']; }, $legacyRows)) . "\n";
}
echo "内置模板: " . implode(', ', array_map(function ($d) { return $d[0]; }, $defs)) . "\n";

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