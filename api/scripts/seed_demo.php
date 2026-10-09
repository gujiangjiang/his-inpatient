<?php
// api/scripts/seed_demo.php - 演示数据（幂等）
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
$pdo->beginTransaction();

// === 基础数据 ===
// 5 科室
$departments = [
    ['code' => 'DEPT01', 'name' => '内科', 'description' => '内科门诊和住院'],
    ['code' => 'DEPT02', 'name' => '外科', 'description' => '外科门诊和住院'],
    ['code' => 'DEPT03', 'name' => '骨科', 'description' => '骨科门诊和住院'],
    ['code' => 'DEPT04', 'name' => '妇产科', 'description' => '妇产科'],
    ['code' => 'DEPT05', 'name' => '儿科', 'description' => '儿科'],
];
foreach ($departments as $d) {
    $check = $pdo->prepare("SELECT id FROM departments WHERE code = ?");
    $check->execute([$d['code']]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO departments (code, name, description, is_active, created_at) VALUES (?,?,?,?,?)")
            ->execute([$d['code'], $d['name'], $d['description'], 1, DateHelper::now()]);
    }
}

// 3 病区
$wards = [
    ['code' => 'WARD01', 'name' => '1病区', 'floor' => '1楼', 'bed_count' => 30],
    ['code' => 'WARD02', 'name' => '2病区', 'floor' => '2楼', 'bed_count' => 30],
    ['code' => 'WARD03', 'name' => '3病区', 'floor' => '3楼', 'bed_count' => 20],
];
foreach ($wards as $w) {
    $check = $pdo->prepare("SELECT id FROM wards WHERE code = ?");
    $check->execute([$w['code']]);
    if (!$check->fetch()) {
        $pdo->prepare("INSERT INTO wards (code, name, floor, bed_count, is_active, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$w['code'], $w['name'], $w['floor'], $w['bed_count'], 1, DateHelper::now()]);
    }
}

// 80 床位
$wardIds = $pdo->query("SELECT id, bed_count FROM wards")->fetchAll(PDO::FETCH_ASSOC);
foreach ($wardIds as $w) {
    for ($i = 1; $i <= $w['bed_count']; $i++) {
        $check = $pdo->prepare("SELECT id FROM beds WHERE ward_id = ? AND bed_no = ?");
        $check->execute([$w['id'], $i]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO beds (ward_id, bed_no, status) VALUES (?, ?, 'free')")
                ->execute([$w['id'], $i]);
        }
    }
}

// === 7 个演示账号 (密码统一 123456) ===
$users = [
    ['username' => 'admin', 'name' => '系统管理员', 'role' => 'admin', 'password' => password_hash('123456', PASSWORD_DEFAULT)],
    ['username' => 'doctor1', 'name' => '王医生', 'role' => 'doctor', 'department_id' => 1, 'password' => password_hash('123456', PASSWORD_DEFAULT)],
    ['username' => 'doctor2', 'name' => '李医生', 'role' => 'doctor', 'department_id' => 2, 'password' => password_hash('123456', PASSWORD_DEFAULT)],
    ['username' => 'nurse1', 'name' => '张护士', 'role' => 'nurse', 'ward_id' => 1, 'password' => password_hash('123456', PASSWORD_DEFAULT)],
    ['username' => 'nurse2', 'name' => '李护士', 'role' => 'nurse', 'ward_id' => 2, 'password' => password_hash('123456', PASSWORD_DEFAULT)],
    ['username' => 'pharma1', 'name' => '药师', 'role' => 'pharmacist', 'password' => password_hash('123456', PASSWORD_DEFAULT)],
    ['username' => 'labtech1', 'name' => '检验技师', 'role' => 'lab_tech', 'password' => password_hash('123456', PASSWORD_DEFAULT)],
];
foreach ($users as $u) {
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$u['username']]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, department_id, ward_id, is_active, created_at) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $u['username'], $u['password'], $u['name'], $u['role'],
            $u['department_id'] ?? null, $u['ward_id'] ?? null, 1, DateHelper::now()
        ]);
    }
}

// === 医药品库存 (含剂型/厂家/药房/基药等级/默认用法) ===
$meds = [
    ['MED001', '0.9%氯化钠注射液100ml(双管软袋)', '100ml', '袋', 3.50, 49780, '注射液', '安徽丰原', '中心药房', '基', 'ljh', '', '静脉输液', '100ml', 0],
    ['MED002', '0.9%氯化钠注射液250ml(双管软袋)', '250ml', '袋', 4.20, 19926, '注射液', '安徽丰原', '中心药房', '基', 'ljh', '', '静脉输液', '250ml', 0],
    ['MED003', '美索巴莫注射液', '1g', '支', 28.00, 135, '注射液', '江苏吴中', '中心药房', '国', 'msbm', 'qd', '静脉输液', '1g', 0],
    ['MED004', '地佐辛注射液', '5mg', '支', 96.00, 303, '粉针', '扬子药业', '中心药房', '', 'dzx', 'q8h', '静脉输液', '5mg', 0],
    ['MED005', '注射用头孢拉定', '0.5g', '支', 3.60, 1046, '粉针', '福抗药业', '中心药房', '省', 'zszbld', 'bid', '静脉输液', '1g', 1],
    ['MED006', '注射用艾普拉唑', '10mg', '瓶', 88.00, 705, '粉针', '丽珠集团', '中心药房', '谈', 'aplz', 'qd', '静脉输液', '10mg', 0],
    ['MED007', '注射用盐酸丙帕他莫', '1g', '支', 32.00, 678, '注射液', '山东瑞阳', '中心药房', '', 'zybbtm', 'bid', '静脉输液', '2g', 0],
    ['MED008', '注射用鹿瓜多肽', '16mg', '支', 45.00, 260, '粉针', '江世药业', '中心药房', '', 'zysgdt', 'qd', '静脉输液', '16mg', 0],
    ['MED009', '乳酸钠林格注射液', '500ml', '袋', 6.80, 320, '注射液', '安徽双鹤', '中心药房', '基', 'rsnlg', '', '静脉输液', '500ml', 0],
    ['MED010', '艾瑞昔布片', '0.1g', '片', 4.20, 800, '片剂', '江苏恒瑞', '中心药房', '国', 'arxb', 'bid', '口服', '0.1g', 0],
    ['MED011', '云南白药胶囊', '0.5g', '粒', 1.60, 1200, '胶囊', '云南白药', '中心药房', '基', 'ynbyjn', 'tid', '口服', '0.5g', 0],
    ['MED012', '地奥司明片', '0.45g', '片', 2.80, 600, '片剂', '南京正大', '中心药房', '省', 'dasmp', 'bid', '口服', '0.45g', 0],
    ['MED013', '弹力袜', '均码', '双', 180.00, 40, '耗材', '', '中心药房', '', 'tlw', '', '外用', '1双', 0],
];
foreach ($meds as $m) {
    $check = $pdo->prepare("SELECT id FROM medications WHERE code = ?");
    $check->execute([$m[0]]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO medications (code, name, specification, unit, price, stock_quantity, dosage_form, manufacturer, pharmacy, drug_class, pinyin_code, default_freq, default_route, default_dosage, is_antibiotic, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute(array_merge($m, [DateHelper::now()]));
    }
}

// === 8 名患者 (含护理等级/费别/过敏/VTE/费用) ===
$patients = [
    ['name' => '张明', 'gender' => 'male', 'birth_date' => '1965-08-15', 'id_card' => '110101196508151234', 'department_id' => 1, 'ward_id' => 1, 'bed_no' => '1', 'admission_diagnosis' => '2型糖尿病伴高血压', 'admission_icd_code' => 'E11.9', 'nursing_level' => '1', 'fee_type' => '居民医保', 'vte_caprini' => '中危', 'vte_bleeding' => '低危', 'total_fee' => 8642.15],
    ['name' => '王芳', 'gender' => 'female', 'birth_date' => '1972-03-22', 'id_card' => '110101197203225678', 'department_id' => 2, 'ward_id' => 1, 'bed_no' => '2', 'admission_diagnosis' => '股骨颈骨折', 'admission_icd_code' => 'S72.0', 'nursing_level' => '1', 'fee_type' => '职工医保', 'vte_caprini' => '高危', 'vte_bleeding' => '中危', 'total_fee' => 24380.50, 'is_critical' => 1],
    ['name' => '李强', 'gender' => 'male', 'birth_date' => '1985-11-30', 'id_card' => '110101198511309012', 'department_id' => 2, 'ward_id' => 2, 'bed_no' => '10', 'admission_diagnosis' => '阑尾炎', 'admission_icd_code' => 'K53.9', 'nursing_level' => '2', 'fee_type' => '自费', 'vte_caprini' => '低危', 'vte_bleeding' => '低危', 'total_fee' => 5120.00],
    ['name' => '赵敏', 'gender' => 'female', 'birth_date' => '1990-06-10', 'id_card' => '110101199006103456', 'department_id' => 4, 'ward_id' => 2, 'bed_no' => '11', 'admission_diagnosis' => '急性阑尾炎', 'admission_icd_code' => 'K53.9', 'nursing_level' => '2', 'fee_type' => '居民医保', 'vte_caprini' => '低危', 'vte_bleeding' => '低危', 'total_fee' => 4360.80],
    ['name' => '陈医生', 'gender' => 'male', 'birth_date' => '1955-01-05', 'id_card' => '110101195501057890', 'department_id' => 1, 'ward_id' => 3, 'bed_no' => '21', 'admission_diagnosis' => '慢性支气管炎', 'admission_icd_code' => 'J42', 'nursing_level' => '3', 'fee_type' => '职工医保', 'vte_caprini' => '中危', 'vte_bleeding' => '低危', 'total_fee' => 3210.40],
    ['name' => '刘伟', 'gender' => 'male', 'birth_date' => '1988-09-18', 'id_card' => '110101198809181234', 'department_id' => 3, 'ward_id' => 3, 'bed_no' => '22', 'admission_diagnosis' => '胫腓骨骨折', 'admission_icd_code' => 'S82.0', 'nursing_level' => '1', 'fee_type' => '居民医保', 'allergy_history' => '青霉素过敏', 'vte_caprini' => '中危', 'vte_bleeding' => '低危', 'total_fee' => 11642.66],
    ['name' => '林丽', 'gender' => 'female', 'birth_date' => '1978-04-25', 'id_card' => '110101197804255678', 'department_id' => 4, 'ward_id' => 3, 'bed_no' => '23', 'admission_diagnosis' => '妊娠期高血压', 'admission_icd_code' => 'O14.9', 'nursing_level' => '2', 'fee_type' => '居民医保', 'vte_caprini' => '高危', 'vte_bleeding' => '中危', 'total_fee' => 6890.30],
    ['name' => '孙强', 'gender' => 'male', 'birth_date' => '2015-12-12', 'id_card' => '110101201512121234', 'department_id' => 5, 'ward_id' => 3, 'bed_no' => '24', 'admission_diagnosis' => '上呼吸道感染', 'admission_icd_code' => 'J00', 'nursing_level' => '3', 'fee_type' => '自费', 'vte_caprini' => '低危', 'vte_bleeding' => '低危', 'total_fee' => 1580.20],
];

$i = 1;
foreach ($patients as $p) {
    $patientNo = 'P' . date('Ymd') . str_pad($i, 4, '0', STR_PAD_LEFT);
    $admissionNo = 'A' . date('Ymd') . str_pad($i, 4, '0', STR_PAD_LEFT);
    $check = $pdo->prepare("SELECT id FROM patients WHERE admission_no = ?");
    $check->execute([$admissionNo]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO patients (patient_no, name, gender, birth_date, id_card, admission_no, department_id, ward_id, bed_no, admission_date, admission_diagnosis, admission_icd_code, nursing_level, fee_type, allergy_history, is_critical, vte_caprini, vte_bleeding, total_fee, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $patientNo, $p['name'], $p['gender'], $p['birth_date'], $p['id_card'],
            $admissionNo, $p['department_id'], $p['ward_id'], $p['bed_no'],
            DateHelper::now(), $p['admission_diagnosis'], $p['admission_icd_code'],
            $p['nursing_level'] ?? '3', $p['fee_type'] ?? '', $p['allergy_history'] ?? '',
            $p['is_critical'] ?? 0, $p['vte_caprini'] ?? '', $p['vte_bleeding'] ?? '',
            $p['total_fee'] ?? 0, 'active', DateHelper::now()
        ]);
        $pid = $pdo->lastInsertId();

        // 更新床位
        $pdo->prepare("UPDATE beds SET status = 'occupied', patient_id = ? WHERE ward_id = ? AND bed_no = ?")
            ->execute([$pid, $p['ward_id'], $p['bed_no']]);
        $i++;
    }
}

// === 标记 setup_completed ===
DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
    ['setup_completed', '1', 'system', '系统是否已初始化', DateHelper::now()]);

$pdo->commit();
echo "演示数据导入完成。\n";
echo "账号：admin/doctor1/doctor2/nurse1/nurse2/pharma1/labtech1，密码：123456\n";
