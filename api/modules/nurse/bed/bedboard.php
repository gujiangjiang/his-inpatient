<?php
// api/modules/nurse/bed/bedboard.php - 护士站床位一览表 (主入口数据源)
// 返回: 病区列表(含占用汇总) + 每个病区的床位卡片数据
// 床位卡片: 患者信息 + 待办徽章 (待核查医嘱/未发药药品/待采集检验/待报告检查)
Auth::requireRole(['admin', 'nurse']);
$user = Auth::user();
$pdo = DB::getPDO();

// ---- 病区范围: 护士限定本人病区; admin 全部激活病区 ----
if ($user['role'] === 'nurse') {
    $wards = DB::select("SELECT w.*, d.name AS department_name FROM wards w
                         LEFT JOIN departments d ON w.department_id = d.id
                         WHERE w.id = ? AND w.is_active = 1", [$user['ward_id']]);
} else {
    $wards = DB::select("SELECT w.*, d.name AS department_name FROM wards w
                         LEFT JOIN departments d ON w.department_id = d.id
                         WHERE w.is_active = 1 ORDER BY w.id");
}

// 病区占用汇总
foreach ($wards as &$w) {
    $stat = DB::selectOne("SELECT COUNT(*) AS total,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied
        FROM beds WHERE ward_id = ?", [$w['id']]);
    $w['bed_total'] = (int)($stat['total'] ?? 0);
    $w['bed_occupied'] = (int)($stat['occupied'] ?? 0);
}
unset($w);

// ---- 每个病区的床位列表 (含患者 + 待办计数) ----
$result = [];
foreach ($wards as $w) {
    $beds = DB::select(
        "SELECT b.*, p.name AS patient_name, p.gender, p.birth_date, p.admission_no,
                p.admission_diagnosis, p.nursing_level, p.is_critical, p.allergy_history,
                p.fee_type, p.admission_date, p.status AS patient_status, w.name AS ward_name
         FROM beds b
         LEFT JOIN patients p ON b.patient_id = p.id
         LEFT JOIN wards w ON b.ward_id = w.id
         WHERE b.ward_id = ?
         ORDER BY CAST(b.bed_no AS INTEGER), b.bed_no", [$w['id']]);

    // 批量取待办计数 (按病区一次聚合, 避免逐床 N+1)
    $patientIds = [];
    foreach ($beds as $b) { if ($b['patient_id']) $patientIds[] = (int)$b['patient_id']; }

    $pending = [];     // 待核查/待作废/待停止医嘱
    $undispensed = []; // 已核对未发药药品
    $labCollect = [];  // 检验待采集
    $examPending = []; // 检查待执行
    $docCount = [];    // 护理文书数量

    if ($patientIds) {
        $ph = implode(',', array_fill(0, count($patientIds), '?'));
        foreach (DB::select("SELECT patient_id, COUNT(*) c FROM orders
                WHERE patient_id IN ($ph) AND status IN ('pending','invalid_pending','stop_pending')
                GROUP BY patient_id", $patientIds) as $r) {
            $pending[(int)$r['patient_id']] = (int)$r['c'];
        }
        foreach (DB::select("SELECT o.patient_id, COUNT(*) c FROM orders o
                WHERE o.patient_id IN ($ph) AND o.category = 'medication' AND o.status IN ('verified','executing')
                  AND o.dispense_status IN ('none','prepared')
                  AND o.billing_flags NOT LIKE '%不摆药%'
                GROUP BY o.patient_id", $patientIds) as $r) {
            $undispensed[(int)$r['patient_id']] = (int)$r['c'];
        }
        foreach (DB::select("SELECT patient_id, COUNT(*) c FROM lab_reports
                WHERE patient_id IN ($ph) AND status = 'pending' GROUP BY patient_id", $patientIds) as $r) {
            $labCollect[(int)$r['patient_id']] = (int)$r['c'];
        }
        foreach (DB::select("SELECT patient_id, COUNT(*) c FROM exam_reports
                WHERE patient_id IN ($ph) AND status = 'pending' GROUP BY patient_id", $patientIds) as $r) {
            $examPending[(int)$r['patient_id']] = (int)$r['c'];
        }
        foreach (DB::select("SELECT patient_id, COUNT(*) c FROM nursing_docs
                WHERE patient_id IN ($ph) GROUP BY patient_id", $patientIds) as $r) {
            $docCount[(int)$r['patient_id']] = (int)$r['c'];
        }
    }

    foreach ($beds as &$b) {
        $pid = (int)$b['patient_id'];
        $b['pending_cnt'] = $pending[$pid] ?? 0;
        $b['undispensed_cnt'] = $undispensed[$pid] ?? 0;
        $b['lab_collect_cnt'] = $labCollect[$pid] ?? 0;
        $b['exam_pending_cnt'] = $examPending[$pid] ?? 0;
        $b['nursing_doc_cnt'] = $docCount[$pid] ?? 0;
        $b['age'] = AgeHelper::display($b['birth_date'] ?? '');
    }
    unset($b);

    $result[] = ['ward' => $w, 'beds' => $beds];
}

Response::success($result);
