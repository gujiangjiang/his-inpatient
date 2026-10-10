<?php
// api/core/helpers/department_helper.php - 数据权限: 医生可管辖科室范围

/**
 * 当前用户可管辖的全部科室 ID
 * = 本人所属科室 + doctor_department_permissions 授权科室 (去重)
 */
function allowed_department_ids($user) {
    $ids = [];
    if (!empty($user['department_id'])) {
        $ids[] = (int)$user['department_id'];
    }
    $rows = DB::select("SELECT department_id FROM doctor_department_permissions WHERE doctor_id = ?", [$user['id']]);
    foreach ($rows as $r) {
        $ids[] = (int)$r['department_id'];
    }
    return array_values(array_unique($ids));
}

/**
 * 医生是否可访问指定科室 (admin 恒可)
 */
function can_access_department($user, $departmentId) {
    if ($user['role'] === 'admin') return true;
    if ($user['role'] !== 'doctor') return false;
    return in_array((int)$departmentId, allowed_department_ids($user), true);
}

/**
 * 当前用户是否可访问指定患者
 * admin 恒可; 护士限本病区; 医生严格限授权科室范围 (不因指派跨科室放行)
 */
function can_access_patient($user, $patientId) {
    if ($user['role'] === 'admin') return true;
    $patient = DB::selectOne("SELECT id, department_id, ward_id FROM patients WHERE id = ?", [$patientId]);
    if (!$patient) return false;
    if ($user['role'] === 'nurse') {
        return (int)$patient['ward_id'] === (int)$user['ward_id'];
    }
    if ($user['role'] === 'doctor') {
        return can_access_department($user, $patient['department_id']);
    }
    return false;
}
