<?php
// api/scripts/seed_demo_orders.php - 医嘱工作站演示数据 (幂等, 无医嘱时导入)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers/time_helper.php';
require_once __DIR__ . '/../core/helpers/date_helper.php';

$pdo = DB::getPDO();

$meds = [];
foreach (DB::select("SELECT id, code FROM medications") as $m) {
    $meds[$m['code']] = $m['id'];
}

// === 医嘱组套 (幂等, 按名称) ===
$sets = [
    ['骨科术后抗感染组套', 'medication', [
        ['[基]0.9%氯化钠注射液100ml(双管软袋)', 'MED001', '100ml', '袋', '静脉输液', '60滴/分', 'bid', ''],
        ['注射用头孢拉定', 'MED005', '1g', '支', '静脉输液', '60滴/分', 'bid', '径外'],
    ]],
    ['VTE 预防组套', 'nonmedication', [
        ['弹力袜', null, '', '', '外用', '', '', '径外'],
        ['踝泵运动指导', null, '', '', '护理', '', 'bid', ''],
    ]],
    ['一级护理常规', 'nonmedication', [
        ['一级护理', null, '', '', '护理', '', '', ''],
        ['测血压', null, '', '', '测量', '', 'bid', ''],
        ['普食饮食', null, '', '', '饮食', '', '', ''],
    ]],
];
$creator = DB::selectOne("SELECT id FROM users WHERE username = 'doctor1'")['id'];
foreach ($sets as $s) {
    if (DB::selectOne("SELECT id FROM order_sets WHERE name = ?", [$s[0]])) continue;
    $pdo->prepare("INSERT INTO order_sets (name, category, is_active, created_by, created_at) VALUES (?,?,?,?,?)")
        ->execute([$s[0], $s[1], 1, $creator, DateHelper::now()]);
    $setId = $pdo->lastInsertId();
    foreach ($s[2] as $i => $item) {
        $pdo->prepare("INSERT INTO order_set_items (set_id, category, drug_id, content, dosage, dosage_unit, usage_name, usage_rate, freq_code, order_period, group_seq, sort_no) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$setId, $s[1], $item[1] ? ($meds[$item[1]] ?? null) : null, $item[0], $item[2], $item[3], $item[4], $item[5], $item[6], 'long', $i, $i]);
    }
}

// === 非药品医嘱目录 (幂等, 按内容) ===
// 原则: 非药品默认不设途径 (仅特殊项如弹力袜/蛋白粉需要时手动选择); 类别仅用于搜索展示, 不写入医嘱
$catalog = [
    ['特级护理', '', '', '', '', 'long', '护理'],
    ['一级护理', '', '', '', '', 'long', '护理'],
    ['二级护理', '', '', '', '', 'long', '护理'],
    ['三级护理', '', '', '', '', 'long', '护理'],
    ['普食饮食', '', '', '6小时后', '', 'long', '饮食'],
    ['糖尿病饮食', '', '', '', '', 'long', '饮食'],
    ['低盐低脂饮食', '', '', '', '', 'long', '饮食'],
    ['测血压', '', 'bid', '6-14', '', 'long', '测量'],
    ['测血氧饱和度', '', 'q2h', '', '不计价', 'long', '测量'],
    ['血糖监测', '', 'qid', '7-11-17-21', '', 'long', '测量'],
    ['心电监护', '', '', '', '', 'long', '监护'],
    ['吸氧 2L/min', '', '', '', '', 'long', '治疗'],
    ['雾化吸入', '', 'bid', '8-16', '', 'long', '治疗'],
    ['陪客一人', '', '', '', '不计价,径外', 'long', '陪护'],
    ['记24小时出入量', '', '', '', '', 'long', '护理'],
    ['健康宣教', '', '', '', '', 'long', '宣教'],
    ['留置针护理', '', '', '', '', 'long', '护理'],
    ['会阴护理', '', 'bid', '', '', 'long', '护理'],
    ['压疮护理', '', 'q2h', '', '', 'long', '护理'],
    ['弹力袜', '外用', '', '', '径外', 'temp', '材料'],
    ['蛋白粉', '', '', '', '', 'long', '材料'],
    ['三角巾悬吊', '', '', '', '', 'temp', '治疗'],
    ['换药处理', '', '', '', '', 'temp', '治疗'],
    ['心电图检查', '', '', '', '', 'temp', '检查'],
    ['胸片检查', '', '', '', '', 'temp', '检查'],
];
foreach ($catalog as $i => $c) {
    if (DB::selectOne("SELECT id FROM order_catalog WHERE content = ?", [$c[0]])) continue;
    $pdo->prepare("INSERT INTO order_catalog (content, category, usage_name, freq_code, freq_detail, billing_flags, order_period, sort_no, is_active, catalog_group, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$c[0], 'general', $c[1], $c[2], $c[3], $c[4], $c[5], $i, 1, $c[6], DateHelper::now()]);
}

if (DB::selectOne("SELECT COUNT(*) as c FROM orders")['c'] > 0) {
    echo "医嘱数据已存在，跳过。\n";
    exit;
}

$patients = DB::select("SELECT id, admission_no, name FROM patients ORDER BY id");
$doctor1 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor1'");
$doctor2 = DB::selectOne("SELECT id FROM users WHERE username = 'doctor2'");

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
