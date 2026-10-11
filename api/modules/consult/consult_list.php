<?php
// api/modules/consult/consult_list.php - 会诊列表 (box=sent 我发起的 / received 请我会诊)
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
Response::success(DB::select($sql, $params));
