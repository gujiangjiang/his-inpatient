<?php
// api/modules/emr/diagnosis_delete.php - 删除患者诊断
Auth::requireRole(['admin', 'doctor']);
$user = Auth::user();
$id = (int)Validator::get('id', 0);
$row = DB::selectOne("SELECT * FROM patient_diagnoses WHERE id = ?", [$id]);
if (!$row) Response::error('诊断不存在', 404);
if (!can_access_patient($user, $row['patient_id'])) Response::error('无权操作该患者', 403);

/* 修正诊断维持约束: 已创建修正诊断时, 初步/入院诊断至少保留一项 (不能只留修正诊断) */
if (in_array($row['category'], ['initial', 'admission'], true)) {
    $hasCorrected = (int)(DB::selectOne(
        "SELECT COUNT(*) AS n FROM patient_diagnoses WHERE patient_id = ? AND category = 'corrected'",
        [$row['patient_id']]
    )['n'] ?? 0);
    if ($hasCorrected > 0 && !emr_diag_has_initial_or_admission($row['patient_id'], (int)$id)) {
        Response::error('已存在修正诊断：初步诊断与入院诊断需至少保留一项（可改为在修正诊断中记录）');
    }
}

DB::execute("DELETE FROM patient_diagnoses WHERE id = ?", [$id]);
/* 主档展示诊断按优先级重算 (回到下一优先级有效诊断) */
emr_diag_recompute_patient($row['patient_id']);
Response::success(null, '已删除');
