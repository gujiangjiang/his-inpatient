<?php
// api/modules/lab/lab_detail.php
Auth::requireRole(['admin', 'doctor', 'nurse', 'lab_tech']);
$id = Validator::get('id');

if (!$id) Response::error('请输入检验单ID');

$report = DB::selectOne("SELECT lr.*, p.name as patient_name, p.gender, p.birth_date, p.bed_no, p.patient_no, p.admission_no,
        d.name AS department_name, w.name AS ward_name, u.name as creator_name, pu.name AS barcode_printed_name,
        (SELECT lc.category FROM lab_catalog lc WHERE lc.name = lr.exam_name ORDER BY lc.id LIMIT 1) AS lab_category
    FROM lab_reports lr
    LEFT JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN wards w ON p.ward_id = w.id
    LEFT JOIN users u ON lr.created_by = u.id
    LEFT JOIN users pu ON lr.barcode_printed_by = pu.id
    WHERE lr.id = ?", [$id]);

if (!$report) Response::error('检验单不存在', 404);
$user = Auth::user();
if (in_array($user['role'], ['nurse', 'doctor'], true)
    && !can_access_patient($user, (int)$report['patient_id'])) {
    Response::error('无权查看该患者检验单', 403);
}

$items = DB::select("SELECT * FROM lab_report_items WHERE report_id = ? ORDER BY id ASC", [$id]);
$report['items'] = $items;
$report['application_snapshot'] = print_snapshot_get('lab_application', $id);
$report['report_snapshot'] = print_snapshot_get('lab_report', $id);

Response::success($report);
