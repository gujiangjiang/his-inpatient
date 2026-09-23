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

// === 医药品库存 ===
$meds = [
    ['code' => 'MED001', 'name' => '5%葡萄糖 250ml', 'specification' => '250ml', 'unit' => '袋', 'price' => 5.00, 'stock_quantity' => 50],
    ['code' => 'MED002', 'name' => '头孢呋辛 1.5g', 'specification' => '1.5g/管', 'unit' => '管', 'price' => 15.00, 'stock_quantity' => 30],
    ['code' => 'MED003', 'name' => '阿司匹林 100mg', 'specification' => '100mg/片', 'unit' => '片', 'price' => 0.50, 'stock_quantity' => 200],
];
foreach ($meds as $m) {
    $check = $pdo->prepare("SELECT id FROM medications WHERE code = ?");
    $check->execute([$m['code']]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO medications (code, name, specification, unit, price, stock_quantity, created_at) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$m['code'], $m['name'], $m['specification'], $m['unit'], $m['price'], $m['stock_quantity'], DateHelper::now()]);
    }
}

// === 8 名患者 ===
$patients = [
    ['name' => '张明', 'gender' => 'male', 'birth_date' => '1965-08-15', 'id_card' => '110101196508151234', 'department_id' => 1, 'ward_id' => 1, 'bed_no' => '1', 'admission_diagnosis' => '2型糖尿病伴高血压', 'admission_icd_code' => 'E11.9'],
    ['name' => '王芳', 'gender' => 'female', 'birth_date' => '1972-03-22', 'id_card' => '110101197203225678', 'department_id' => 2, 'ward_id' => 1, 'bed_no' => '2', 'admission_diagnosis' => '股骨颈骨折', 'admission_icd_code' => 'S72.0'],
    ['name' => '李强', 'gender' => 'male', 'birth_date' => '1985-11-30', 'id_card' => '110101198511309012', 'department_id' => 2, 'ward_id' => 2, 'bed_no' => '10', 'admission_diagnosis' => '阑尾炎', 'admission_icd_code' => 'K53.9'],
    ['name' => '赵敏', 'gender' => 'female', 'birth_date' => '1990-06-10', 'id_card' => '110101199006103456', 'department_id' => 4, 'ward_id' => 2, 'bed_no' => '11', 'admission_diagnosis' => '急性阑尾炎', 'admission_icd_code' => 'K53.9'],
    ['name' => '陈医生', 'gender' => 'male', 'birth_date' => '1955-01-05', 'id_card' => '110101195501057890', 'department_id' => 1, 'ward_id' => 3, 'bed_no' => '21', 'admission_diagnosis' => '慢性支气管炎', 'admission_icd_code' => 'J42'],
    ['name' => '刘伟', 'gender' => 'male', 'birth_date' => '1988-09-18', 'id_card' => '110101198809181234', 'department_id' => 3, 'ward_id' => 3, 'bed_no' => '22', 'admission_diagnosis' => '胫髓髎骨折', 'admission_icd_code' => 'S82.0'],
    ['name' => '林丽', 'gender' => 'female', 'birth_date' => '1978-04-25', 'id_card' => '110101197804255678', 'department_id' => 4, 'ward_id' => 3, 'bed_no' => '23', 'admission_diagnosis' => '妊娠期高血压', 'admission_icd_code' => 'O14.9'],
    ['name' => '孙强', 'gender' => 'male', 'birth_date' => '2015-12-12', 'id_card' => '110101201512121234', 'department_id' => 5, 'ward_id' => 3, 'bed_no' => '24', 'admission_diagnosis' => '上呼吸道感染', 'admission_icd_code' => 'J00'],
];

$i = 1;
foreach ($patients as $p) {
    $patientNo = 'P' . date('Ymd') . str_pad($i, 4, '0', STR_PAD_LEFT);
    $admissionNo = 'A' . date('Ymd') . str_pad($i, 4, '0', STR_PAD_LEFT);
    $check = $pdo->prepare("SELECT id FROM patients WHERE admission_no = ?");
    $check->execute([$admissionNo]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO patients (patient_no, name, gender, birth_date, id_card, admission_no, department_id, ward_id, bed_no, admission_date, admission_diagnosis, admission_icd_code, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $patientNo, $p['name'], $p['gender'], $p['birth_date'], $p['id_card'],
            $admissionNo, $p['department_id'], $p['ward_id'], $p['bed_no'],
            DateHelper::now(), $p['admission_diagnosis'], $p['admission_icd_code'],
            'active', DateHelper::now()
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
