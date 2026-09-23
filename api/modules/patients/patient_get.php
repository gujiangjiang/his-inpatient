<?php
// api/modules/patients/patient_get.php
$roles = ['admin', 'doctor', 'nurse'];
Auth::requireRole($roles);
$user = Auth::user();
$pdo = DB::getPDO();

$id = Validator::get('id');
$admissionNo = Validator::get('admission_no');

if ($id) {
    $stmt = $pdo->prepare("SELECT p.*, d.name as department_name, w.name as ward_name 
        FROM patients p 
        LEFT JOIN departments d ON p.department_id = d.id 
        LEFT JOIN wards w ON p.ward_id = w.id 
        WHERE p.id = ?");
    $stmt->execute([$id]);
} elseif ($admissionNo) {
    $stmt = $pdo->prepare("SELECT p.*, d.name as department_name, w.name as ward_name 
        FROM patients p 
        LEFT JOIN departments d ON p.department_id = d.id 
        LEFT JOIN wards w ON p.ward_id = w.id 
        WHERE p.admission_no = ?");
    $stmt->execute([$admissionNo]);
} else {
    Response::error('请输入患者ID或住院号');
}

$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    Response::error('患者不存在', 404);
}

// 权限校验：护士只能查看本病区患者；医生可查看本科室患者或本人指派管辖患者
if ($user['role'] === 'nurse' && $patient['ward_id'] != $user['ward_id']) {
    Response::error('权限不足', 403);
}
if ($user['role'] === 'doctor') {
    $allowed = ($patient['department_id'] == $user['department_id']);
    if (!$allowed) {
        // 本人指派管辖 (主管或上级医师)
        $assign = DB::selectOne("SELECT id FROM patient_assignments WHERE patient_id = ? AND (attending_doctor_id = ? OR senior_doctor_id = ?)",
            [$patient['id'], $user['id'], $user['id']]);
        if ($assign) $allowed = true;
    }
    if (!$allowed) {
        Response::error('权限不足', 403);
    }
}

Response::success($patient);
