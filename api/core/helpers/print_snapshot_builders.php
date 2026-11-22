<?php
// api/core/helpers/print_snapshot_builders.php - 各类医生打印文档的不可变数据构建器

function print_snapshot_lab_payload($id) {
    $row = DB::selectOne(
        "SELECT lr.*, cu.name AS creator_name, ru.name AS reported_name,
                (SELECT lc.category FROM lab_catalog lc WHERE lc.name = lr.exam_name ORDER BY lc.id LIMIT 1) AS lab_category,
                (SELECT lc.dept FROM lab_catalog lc WHERE lc.name = lr.exam_name ORDER BY lc.id LIMIT 1) AS exec_dept
         FROM lab_reports lr
         LEFT JOIN users cu ON cu.id = lr.created_by
         LEFT JOIN users ru ON ru.id = lr.reported_by
         WHERE lr.id = ?", [(int)$id]
    );
    if (!$row) throw new RuntimeException('检验单不存在');
    $row['items'] = DB::select("SELECT item_name, result, unit, reference_range, flag FROM lab_report_items WHERE report_id = ? ORDER BY id", [(int)$id]);
    return print_snapshot_with_context($row, $row['patient_id'], TimeHelper::nowLocal());
}

function print_snapshot_exam_payload($id) {
    $row = DB::selectOne(
        "SELECT er.*, cu.name AS creator_name, ru.name AS reported_name,
                (SELECT ec.dept FROM exam_catalog ec WHERE ec.name = er.exam_type ORDER BY ec.id LIMIT 1) AS exec_dept,
                (SELECT ec.category FROM exam_catalog ec WHERE ec.name = er.exam_type ORDER BY ec.id LIMIT 1) AS exam_category
         FROM exam_reports er
         LEFT JOIN users cu ON cu.id = er.created_by
         LEFT JOIN users ru ON ru.id = er.reported_by
         WHERE er.id = ?", [(int)$id]
    );
    if (!$row) throw new RuntimeException('检查单不存在');
    return print_snapshot_with_context($row, $row['patient_id'], TimeHelper::nowLocal());
}

function print_snapshot_consult_payload($id) {
    $row = DB::selectOne("SELECT * FROM consultations WHERE id = ?", [(int)$id]);
    if (!$row) throw new RuntimeException('会诊申请不存在');
    return print_snapshot_with_context($row, $row['patient_id'], TimeHelper::nowLocal());
}

function print_snapshot_rx_payload($rxNo) {
    $items = DB::select(
        "SELECT o.*, u.name AS creator_name, m.specification AS spec,
                m.unit, m.price AS unit_price, m.manufacturer
         FROM orders o
         LEFT JOIN users u ON u.id = o.created_by
         LEFT JOIN medications m ON m.id = o.drug_id
         WHERE o.rx_no = ? AND o.notes = '处方'
         ORDER BY o.id", [(string)$rxNo]
    );
    if (!$items) throw new RuntimeException('处方不存在');
    $first = $items[0];
    $total = 0.0;
    foreach ($items as &$item) {
        $item['quantity'] = isset($item['quantity']) ? (float)$item['quantity'] : 1;
        $item['vendor_short'] = (string)($item['manufacturer'] ?? '');
        $item['unit_price'] = (float)($item['unit_price'] ?? 0);
        $item['line_amount'] = $item['unit_price'] * $item['quantity'];
        $total += $item['line_amount'];
    }
    unset($item);
    $pharmacyType = (string)($first['pharmacy_type'] ?? 'center');
    $pharmacyName = $pharmacyType === 'outpatient'
        ? (string)(ConfigDB::get('pharmacy_outpatient_name', '') ?: '门诊药房')
        : (string)(ConfigDB::get('pharmacy_center_name', '') ?: '中心药房');
    return array_merge(print_snapshot_brand(), [
        'rx_no' => (string)$rxNo,
        'patient_id' => (int)$first['patient_id'],
        'admission_no' => (string)$first['admission_no'],
        'created_at' => (string)$first['created_at'],
        'doctor_name' => (string)($first['creator_name'] ?? ''),
        'pharmacy_type' => $pharmacyType,
        'pharmacy_name' => $pharmacyName,
        'total_amount' => $total,
        'patient' => print_snapshot_patient((int)$first['patient_id'], TimeHelper::nowLocal()),
        'items' => $items,
    ]);
}
