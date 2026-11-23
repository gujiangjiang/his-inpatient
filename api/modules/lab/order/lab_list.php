<?php
// api/modules/lab/lab_list.php
// 字段口径与 lab_detail 对齐: 性别/出生日期/科室/病区/检验分类
// (检验条码批量打印直接以列表数据生成标签, 缺字段会导致标签性别年龄科室病区分类为空)
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$patientId = Validator::get('patient_id');
$sql = "SELECT lr.*, p.name as patient_name, p.gender, p.birth_date, p.bed_no, u.name as creator_name,
        pu.name AS barcode_printed_name,
        d.name AS department_name, w.name AS ward_name,
        (SELECT lc.category FROM lab_catalog lc WHERE lc.name = lr.exam_name ORDER BY lc.id LIMIT 1) AS lab_category,
        (SELECT dept FROM lab_catalog WHERE name = lr.exam_name AND opd_id IS NOT NULL LIMIT 1) AS exec_dept
        FROM lab_reports lr
        LEFT JOIN patients p ON lr.patient_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN wards w ON p.ward_id = w.id
        LEFT JOIN users u ON lr.created_by = u.id
        LEFT JOIN users pu ON lr.barcode_printed_by = pu.id";
$params = [];
if ($patientId) {
    $sql .= " WHERE lr.patient_id = ?";
    $params[] = $patientId;
}
$sql .= " ORDER BY lr.created_at DESC";
$stmt = DB::getPDO()->prepare($sql);
$stmt->execute($params);
Response::success($stmt->fetchAll(PDO::FETCH_ASSOC));
