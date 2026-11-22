<?php
// api/modules/nurse/order_sheet.php - 医嘱单打印数据 (长期/临时医嘱单, A4)
// 仅打印已提交且未作废医嘱; 显示完整起止时间/医嘱内容/开嘱医生/核对护士/执行护士
// 返回: { long: [...], temp: [...] } 两条独立医嘱单数据 (各自单独计算页码)
Auth::requireRole(['admin', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('请输入患者ID');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权访问该患者', 403);

$pdo = DB::getPDO();
$patient = DB::selectOne("SELECT p.*, d.name AS department_name, w.name AS ward_name
    FROM patients p
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN wards w ON p.ward_id = w.id
    WHERE p.id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在');

// 医嘱: 与护士站患者医嘱列表采用同一有效状态口径；草稿从未正式提交，不得打印。
// 成组子医嘱也是实际医嘱，必须随根医嘱按组内顺序打印，不能只取 parent_id IS NULL。
// 核对护士 = orders.verified_by; 执行护士 = nursing_records.executed_by (最近一次)
$rows = DB::select(
    "SELECT o.*, u.name AS doctor_name,
            vu.name AS verified_name
     FROM orders o
     LEFT JOIN users u ON o.created_by = u.id
     LEFT JOIN users vu ON o.verified_by = vu.id
      WHERE o.patient_id = ?
        AND o.status IN ('pending', 'invalid_pending', 'stop_pending', 'verified', 'executing', 'completed', 'stopped')
      ORDER BY o.order_period ASC, COALESCE(o.parent_id, o.id) ASC,
               CASE WHEN o.parent_id IS NULL THEN 0 ELSE 1 END ASC, o.group_seq ASC, o.id ASC", [$patientId]);

// 补执行护士 (最近执行记录)
$execMap = [];
if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (DB::select(
        "SELECT nr.order_id, u.name AS exec_name
         FROM nursing_records nr
         LEFT JOIN users u ON nr.executed_by = u.id
         WHERE nr.order_id IN ($ph)
         ORDER BY nr.id DESC", $ids) as $r) {
        if (!isset($execMap[(int)$r['order_id']])) $execMap[(int)$r['order_id']] = $r['exec_name'];
    }
}

$long = []; $temp = [];
foreach ($rows as $r) {
    $item = [
        'id' => $r['id'],
        'content' => $r['content'],
        'category' => $r['category'],
        'dosage' => $r['dosage'],
        'dosage_unit' => $r['dosage_unit'],
        'usage_name' => $r['usage_name'],
        'usage_rate' => $r['usage_rate'],
        'freq_code' => $r['freq_code'],
        'freq_detail' => $r['freq_detail'],
        'order_period' => $r['order_period'],
        'start_date' => $r['start_date'],
        'end_date' => $r['end_date'],
        'stop_date' => $r['stop_date'],
        'status' => $r['status'],
        'doctor_name' => $r['doctor_name'],
        'verified_name' => $r['verified_name'],
        'exec_name' => $execMap[(int)$r['id']] ?? '',
        'billing_flags' => $r['billing_flags'],
    ];
    if ($r['order_period'] === 'temp') $temp[] = $item;
    else $long[] = $item;
}

Response::success([
    'patient' => [
        'id' => (int)$patient['id'], 'name' => $patient['name'], 'gender' => $patient['gender'],
        'birth_date' => $patient['birth_date'], 'admission_no' => $patient['admission_no'],
        'bed_no' => $patient['bed_no'], 'department_name' => $patient['department_name'],
        'ward_name' => $patient['ward_name'], 'admission_diagnosis' => $patient['admission_diagnosis'],
    ],
    'long' => $long,
    'temp' => $temp,
]);
