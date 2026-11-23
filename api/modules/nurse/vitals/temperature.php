<?php
// api/modules/nurse/vitals/temperature.php - 体温单 (从护理记录单派生, 只读)
// 护理记录单是生命体征唯一数据源; 体温单按 日期×时点 汇总展示/打印
// 动作: list 查询 (admin/nurse/doctor)
$action = Validator::get('action', 'list');
$user = Auth::user();
Auth::requireRole(['admin', 'nurse', 'doctor']);

if ($action === 'list') {
    $patientId = Validator::get('patient_id');
    if (!$patientId) Response::error('请输入患者ID');
    if (!can_access_patient($user, (int)$patientId)) Response::error('无权访问该患者', 403);
    $rows = DB::select(
        "SELECT nn.id, nn.record_time, nn.temperature, nn.pulse, nn.respiratory_rate,
                nn.bp_systolic, nn.bp_diastolic, nn.oxygen_saturation, nn.intake_ml, nn.output_ml,
                u.name AS recorder_name
         FROM nursing_notes nn
         LEFT JOIN users u ON nn.recorded_by = u.id
         WHERE nn.patient_id = ?
           AND (nn.temperature IS NOT NULL OR nn.pulse IS NOT NULL OR nn.respiratory_rate IS NOT NULL
                OR nn.bp_systolic IS NOT NULL OR nn.oxygen_saturation IS NOT NULL
                OR nn.intake_ml IS NOT NULL OR nn.output_ml IS NOT NULL)
         ORDER BY nn.record_time ASC, nn.id ASC", [$patientId]);
    Response::success($rows);
}
