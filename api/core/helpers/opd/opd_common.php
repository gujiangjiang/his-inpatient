<?php
// api/core/helpers/opd/opd_common.php - 互通杂项 (时间格式化/标志映射/患者行解析)

/** 对方系统（门诊）时间 → 本系统站点时区显示（Y-m-d H:i）。
 *  三系统契约：外部接口时间一律标准 UTC（ISO 8601 带 Z/偏移）；
 *  strtotime 精确解析时间点后按本系统站点时区渲染展示。 */
function opd_fmt_datetime($iso) {
    if (!$iso) return '';
    $ts = strtotime((string)$iso);
    return $ts ? date('Y-m-d H:i', $ts) : (string)$iso;
}

/** Observation interpretation → HIS 标志 (critical/H/L/'') */
function opd_observation_flag($res) {
    $code = strtoupper((string)($res['interpretation'][0]['coding'][0]['code'] ?? ''));
    if (in_array($code, ['HH', 'LL', 'CRIT', 'PANIC', 'AA', 'CRITICAL'], true)) return 'critical';
    if ($code === 'H' || $code === 'HIGH' || $code === '高') return 'H';
    if ($code === 'L' || $code === 'LOW' || $code === '低') return 'L';
    return '';
}

/** 解析当前用户可见的患者行 (含科室/病区名, 复用 can_access_patient 数据权限) */
function opd_resolve_patient($patientId) {
    $patient = DB::selectOne(
        "SELECT p.*, d.name AS department_name, w.name AS ward_name FROM patients p
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN wards w ON w.id = p.ward_id WHERE p.id = ?",
        [$patientId]
    );
    if (!$patient || !can_access_patient(Auth::user(), $patientId)) return null;
    return $patient;
}
