<?php
// api/modules/consult/list.php - 会诊列表 (box=sent 我发起的 / received 请我会诊)
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$box = Validator::get('box', 'sent');

$sql = "SELECT c.*, p.name AS patient_name, p.bed_no, p.admission_diagnosis
        FROM consultations c LEFT JOIN patients p ON p.id = c.patient_id";
$params = [];
if ($box === 'received') {
    if ($user['role'] === 'admin') {
        // 管理员查看全部
    } else {
        $deptIds = allowed_department_ids($user);
        if (!$deptIds) Response::success([]);
        $ph = implode(',', array_fill(0, count($deptIds), '?'));
        $sql .= " WHERE c.to_dept_id IN ($ph)";
        $params = $deptIds;
    }
} else {
    $sql .= " WHERE c.created_by = ?";
    $params[] = $user['id'];
}
$sql .= " ORDER BY c.id DESC LIMIT 100";
$rows = DB::select($sql, $params);
foreach ($rows as &$row) {
    $row['application_snapshot'] = print_snapshot_get('consult_application', $row['id']);
    $row['report_snapshot'] = print_snapshot_get('consult_report', $row['id']);
}
unset($row);
Response::success($rows);
