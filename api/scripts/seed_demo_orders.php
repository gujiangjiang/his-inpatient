<?php
// api/scripts/seed_demo_orders.php - 医嘱工作站演示数据 (幂等, 无医嘱时导入)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();
if (DB::selectOne("SELECT COUNT(*) as c FROM orders")['c'] > 0) {
    echo "医嘱数据已存在，跳过。\n";
    exit;
}

$patients = DB::select("SELECT id, admission_no, name FROM patients ORDER BY id");
$doctor1 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor1'");
$doctor2 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor2'");
$meds = [];
foreach (DB::select("SELECT id, code FROM medications") as $m) {
    $meds[$m['code']] = $m['id'];
}

/**
 * 插入一条医嘱 (成组时首条为主医嘱, 携带组级用法)
 * $spec: [period,status,category,items[]] 或单条 [period,status,category,content,...]
 */
function seed_order($pdo, $patient, $userId, $period, $status, $category, $items, $group = []) {
    $now = DateHelper::now();
    $isGroup = $group !== [];
    $parentId = null;
    foreach ($items as $i => $item) {
        $stmt = $pdo->prepare("INSERT INTO orders (patient_id, admission_no, parent_id, is_group_main, order_type, order_period, content, category, status, drug_id, dosage, dosage_unit, usage_name, usage_rate, freq_code, freq_detail, billing_flags, group_seq, sort_no, start_date, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $patient['id'], $patient['admission_no'],
            $i === 0 ? null : $parentId,
            ($isGroup && $i === 0) ? 1 : 0,
            $category === 'medication' ? 'medication' : 'general',
            $period, $item['content'], $category, $status,
            $item['drug_id'] ?? null, $item['dosage'] ?? '', $item['dosage_unit'] ?? '',
            $item['usage'] ?? ($group['usage'] ?? ''), $item['rate'] ?? ($group['rate'] ?? ''),
            $item['freq'] ?? '', $item['freq_detail'] ?? '', $item['flags'] ?? '',
            $i, 0, $now, $userId, $now, $now
        ]);
        if ($isGroup && $i === 0) $parentId = $pdo->lastInsertId();
    }
}

$d1 = $doctor1['id'];
$d2 = $doctor2['id'];

if (isset($patients[0])) {
    $p = $patients[0];
    $ns = [['content' => '一级护理', 'usage' => '护理'], ['content' => '普食饮食', 'usage' => '饮食', 'freq_detail' => '6小时后']];
    foreach ($ns as $item) seed_order($pdo, $p, $d1, 'long', 'verified', 'general', [$item]);
    seed_order($pdo, $p, $d1, 'long', 'verified', 'general', [['content' => '测血压', 'usage' => '测量', 'freq' => 'bid', 'freq_detail' => '6-14']]);
    seed_order($pdo, $p, $d1, 'long', 'verified', 'general', [['content' => '测血氧饱和度', 'usage' => '测量', 'freq' => 'q2h', 'flags' => '不计价']]);
    seed_order($pdo, $p, $d1, 'long', 'verified', 'medication', [['content' => '艾瑞昔布片', 'drug_id' => $meds['MED010'] ?? null, 'dosage' => '0.1g', 'dosage_unit' => '片', 'usage' => '口服', 'freq' => 'bid', 'freq_detail' => '07-16', 'flags' => '径外']]);
    seed_order($pdo, $p, $d1, 'long', 'pending', 'medication', [['content' => '[基]云南白药胶囊', 'drug_id' => $meds['MED011'] ?? null, 'dosage' => '0.5g', 'dosage_unit' => '粒', 'usage' => '口服', 'freq' => 'tid', 'freq_detail' => '07-11-16']]);
    seed_order($pdo, $p, $d1, 'long', 'pending', 'medication', [['content' => '地奥司明片', 'drug_id' => $meds['MED012'] ?? null, 'dosage' => '0.45g', 'dosage_unit' => '片', 'usage' => '口服', 'freq' => 'bid', 'freq_detail' => '07-16']]);
    seed_order($pdo, $p, $d1, 'temp', 'verified', 'medication', [
        ['content' => '[基]0.9%氯化钠注射液100ml(双管软袋)', 'drug_id' => $meds['MED001'] ?? null, 'dosage' => '100ml', 'dosage_unit' => '袋', 'flags' => '径外'],
        ['content' => '注射用头孢拉定', 'drug_id' => $meds['MED005'] ?? null, 'dosage' => '1g', 'dosage_unit' => '支', 'flags' => '预防,径外'],
    ], ['usage' => '静脉输液', 'rate' => '60滴/分']);
    seed_order($pdo, $p, $d1, 'temp', 'pending', 'medication', [
        ['content' => '[基]0.9%氯化钠注射液100ml(双管软袋)', 'drug_id' => $meds['MED001'] ?? null, 'dosage' => '100ml', 'dosage_unit' => '袋'],
        ['content' => '注射用盐酸丙帕他莫', 'drug_id' => $meds['MED007'] ?? null, 'dosage' => '2g', 'dosage_unit' => '支', 'flags' => '自费'],
    ], ['usage' => '静脉输液', 'rate' => '60滴/分']);
    seed_order($pdo, $p, $d1, 'temp', 'verified', 'general', [['content' => '弹力袜', 'usage' => '外用', 'flags' => '径外']]);
}

if (isset($patients[1])) {
    $p = $patients[1];
    seed_order($pdo, $p, $d1, 'long', 'verified', 'general', [['content' => '一级护理', 'usage' => '护理']]);
    seed_order($pdo, $p, $d1, 'long', 'verified', 'general', [['content' => '陪客一人', 'usage' => '陪护', 'flags' => '不计价,径外']]);
    seed_order($pdo, $p, $d1, 'temp', 'verified', 'medication', [['content' => '[基]乳酸钠林格注射液', 'drug_id' => $meds['MED009'] ?? null, 'dosage' => '500ml', 'dosage_unit' => '袋', 'usage' => '静脉输液', 'rate' => '60滴/分']]);
    seed_order($pdo, $p, $d1, 'temp', 'executing', 'general', [['content' => '三角巾悬吊', 'usage' => '护理']]);
    seed_order($pdo, $p, $d1, 'long', 'draft', 'medication', [['content' => '注射用鹿瓜多肽', 'drug_id' => $meds['MED008'] ?? null, 'dosage' => '16mg', 'dosage_unit' => '支', 'usage' => '静脉输液', 'freq' => 'qd', 'flags' => '自费,径外']]);
}

$fallback = [
    ['注射用美索巴莫', 'MED003', '1g', '支', '静脉输液', 'qd'],
    ['地佐辛注射液', 'MED004', '5mg', '支', '静脉输液', 'q8h'],
    ['注射用艾普拉唑', 'MED006', '10mg', '瓶', '静脉输液', 'qd'],
    ['注射用盐酸丙帕他莫', 'MED007', '2g', '支', '静脉输液', 'bid'],
];
foreach (array_slice($patients, 2) as $idx => $p) {
    $f = $fallback[$idx % count($fallback)];
    $doc = ($idx % 2 === 0) ? $d1 : $d2;
    seed_order($pdo, $p, $doc, $idx % 2 === 0 ? 'long' : 'temp', 'verified', 'medication', [[
        'content' => $f[0], 'drug_id' => $meds[$f[1]] ?? null, 'dosage' => $f[2], 'dosage_unit' => $f[3],
        'usage' => $f[4], 'freq' => $f[5],
    ]]);
    seed_order($pdo, $p, $doc, 'long', 'verified', 'general', [['content' => '二级护理', 'usage' => '护理']]);
}

echo "医嘱工作站演示数据导入完成。\n";
