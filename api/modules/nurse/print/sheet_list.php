<?php
// api/modules/nurse/print/sheet_list.php - 执行单打印: 该患者已核对药品医嘱 (含子医嘱) + 打印状态
// 返回字段兼容医嘱执行贴模板 (PrintLabels.medicationExecution): 患者上下文 + 完整组项
Auth::requireRole(['admin', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('请输入患者ID');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权访问该患者', 403);

$pdo = DB::getPDO();

// 已核对/执行中/已完成的药品根医嘱 (执行单打印对象)
$rows = DB::select(
    "SELECT o.id, o.parent_id, o.is_group_main, o.group_seq, o.content, o.dosage, o.dosage_unit,
            o.usage_name, o.usage_rate, o.freq_code, o.freq_detail, o.status, o.order_period,
            o.dispense_status, o.notes, o.start_date, o.created_at,
            p.name AS patient_name, p.gender, p.birth_date, p.bed_no, p.admission_no,
            d.name AS department_name, w.name AS ward_name,
            u.name AS doctor_name, vu.name AS verified_name,
            (SELECT MAX(dr.id) FROM dispensing_records dr
             WHERE dr.status IN ('dispensed', 'delivered')
               AND (dr.order_id = o.id OR (o.parent_id IS NULL AND dr.order_id IN
                    (SELECT co.id FROM orders co WHERE co.parent_id = o.id)))) AS dispensed_id
     FROM orders o
     LEFT JOIN patients p ON o.patient_id = p.id
     LEFT JOIN departments d ON p.department_id = d.id
     LEFT JOIN wards w ON p.ward_id = w.id
     LEFT JOIN users u ON o.created_by = u.id
     LEFT JOIN users vu ON o.verified_by = vu.id
     WHERE o.patient_id = ?
       AND o.category = 'medication'
       AND o.status IN ('verified', 'executing', 'completed')
       AND (o.parent_id IS NULL OR o.is_group_main = 1)
     ORDER BY o.order_period ASC, o.id ASC",
    [$patientId]
);

// 根医嘱组内子医嘱 (完整字段, 供执行贴模板展示)
$roots = array_values(array_filter($rows, function ($r) { return empty($r['parent_id']) || $r['is_group_main'] == 1; }));
$subsByParent = [];
if ($roots) {
    $rootIds = array_map(function ($r) { return (int)$r['id']; }, $roots);
    $ph = implode(',', array_fill(0, count($rootIds), '?'));
    $subs = DB::select(
        "SELECT id, parent_id, is_group_main, group_seq, content, dosage, dosage_unit,
                usage_name, usage_rate, freq_code, freq_detail, notes, status
         FROM orders WHERE parent_id IN ($ph) ORDER BY group_seq ASC, id ASC",
        $rootIds
    );
    foreach ($subs as $s) $subsByParent[(int)$s['parent_id']][] = $s;
}

// 打印留痕 (按根医嘱)
$printedSet = [];
if ($roots) {
    $rootIds = array_map(function ($r) { return (int)$r['id']; }, $roots);
    $ph = implode(',', array_fill(0, count($rootIds), '?'));
    foreach (DB::select(
        "SELECT order_id, printed_at, u.name AS printed_name
         FROM order_exec_sheet_prints p
         LEFT JOIN users u ON p.printed_by = u.id
         WHERE p.patient_id = ? AND p.order_id IN ($ph)
         ORDER BY p.id DESC", array_merge([$patientId], $rootIds)) as $p) {
        if (!isset($printedSet[(int)$p['order_id']])) {
            $printedSet[(int)$p['order_id']] = [
                'printed_at' => $p['printed_at'],
                'printed_name' => $p['printed_name']
            ];
        }
    }
}

$items = [];
foreach ($roots as $r) {
    $rootItem = [
        'id' => (int)$r['id'],
        'parent_id' => $r['parent_id'],
        'is_group_main' => $r['is_group_main'],
        'group_seq' => $r['group_seq'],
        'content' => $r['content'],
        'dosage' => $r['dosage'],
        'dosage_unit' => $r['dosage_unit'],
        'usage_name' => $r['usage_name'],
        'usage_rate' => $r['usage_rate'],
        'freq_code' => $r['freq_code'],
        'freq_detail' => $r['freq_detail'],
        'notes' => $r['notes'],
        'status' => $r['status'],
    ];
    $subItems = isset($subsByParent[(int)$r['id']]) ? $subsByParent[(int)$r['id']] : [];
    $items[] = [
        'id' => (int)$r['id'],
        'content' => $r['content'],
        'dosage' => $r['dosage'],
        'dosage_unit' => $r['dosage_unit'],
        'usage_name' => $r['usage_name'],
        'usage_rate' => $r['usage_rate'],
        'freq_code' => $r['freq_code'],
        'freq_detail' => $r['freq_detail'],
        'order_period' => $r['order_period'],
        'status' => $r['status'],
        'dispensed' => !empty($r['dispensed_id']),
        /* 执行贴模板字段 */
        'patient_name' => $r['patient_name'],
        'gender' => $r['gender'],
        'birth_date' => $r['birth_date'],
        'bed_no' => $r['bed_no'],
        'admission_no' => $r['admission_no'],
        'department_name' => $r['department_name'],
        'ward_name' => $r['ward_name'],
        'doctor_name' => $r['doctor_name'],
        'verified_name' => $r['verified_name'],
        'start_date' => $r['start_date'],
        'created_at' => $r['created_at'],
        /* 组项: 根医嘱在前, 子医嘱在后 (与执行贴模板 group_items 口径一致) */
        'group_items' => array_merge([$rootItem], $subItems),
        'sub_orders' => $subItems,
        'printed' => isset($printedSet[(int)$r['id']]),
        'printed_at' => $printedSet[(int)$r['id']]['printed_at'] ?? '',
        'printed_name' => $printedSet[(int)$r['id']]['printed_name'] ?? '',
    ];
}

Response::success($items);