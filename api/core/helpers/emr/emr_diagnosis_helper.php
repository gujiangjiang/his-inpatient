<?php
// api/core/helpers/emr/emr_diagnosis_helper.php - 诊断优先级联动 (显示/引用统一口径)
// 优先级: 修正诊断(corrected) > 入院诊断(admission) > 初步诊断(initial) > 门(急)诊诊断(emergency)
// - 患者主档 patients.admission_diagnosis/admission_icd_code = 上述优先级的展示缓存
//   (患者卡片/信息横栏/会诊「因xx入院」等只读该字段; 诊断增删/同步后统一重算)
// - 电子病历自动引用仅取 修正/入院/初步 (不含门急诊), 全空时由前端提示完善诊断
// - 修正诊断约束: 需先有初步或入院诊断; 创建后有修正诊断时, 初步/入院至少保留一项

/** 优先级分类 (高 → 低; 门急诊 = 入院自带诊断) */
function emr_diag_priority_cats($includeEmergency = true)
{
    return $includeEmergency ? ['corrected', 'admission', 'initial', 'emergency'] : ['corrected', 'admission', 'initial'];
}

/** 分类诊断行 [{name, icd_code}] */
function emr_diag_rows_of($patientId, $category)
{
    return DB::select(
        "SELECT name, IFNULL(icd_code, '') AS icd_code FROM patient_diagnoses
          WHERE patient_id = ? AND category = ? ORDER BY sort_no, id",
        [$patientId, $category]
    );
}

/** 诊断文本拼接 (多条加序号: 1.xx；2.xx) */
function emr_diag_join_names(array $rows)
{
    $names = [];
    foreach ($rows as $r) $names[] = (string)$r['name'];
    if (count($names) <= 1) return implode('', $names);
    $out = [];
    foreach ($names as $i => $n) $out[] = ($i + 1) . '.' . $n;
    return implode('；', $out);
}

/** 优先级最高的非空诊断: {category, text, icd, count} 或 null */
function emr_diag_priority($patientId, $includeEmergency = true)
{
    foreach (emr_diag_priority_cats($includeEmergency) as $cat) {
        $rows = emr_diag_rows_of($patientId, $cat);
        if (!$rows) continue;
        $icds = [];
        foreach ($rows as $r) {
            if ((string)$r['icd_code'] !== '') $icds[] = (string)$r['icd_code'];
        }
        return ['category' => $cat, 'text' => emr_diag_join_names($rows), 'icd' => implode(' ', $icds), 'count' => count($rows)];
    }
    return null;
}

/** 重算患者主档展示诊断 (诊断增删/同步后调用; 无任何诊断 → 清空) */
function emr_diag_recompute_patient($patientId)
{
    $top = emr_diag_priority($patientId, true);
    if ($top) {
        DB::execute(
            "UPDATE patients SET admission_diagnosis = ?, admission_icd_code = ? WHERE id = ?",
            [$top['text'], $top['icd'], $patientId]
        );
    } else {
        DB::execute("UPDATE patients SET admission_diagnosis = '', admission_icd_code = '' WHERE id = ?", [$patientId]);
    }
}

/** 入院自带诊断(门急诊分类)入库/同步: 无门急诊行时新增; 仅一条且名称不同则更新; 随后重算主档 */
function emr_diag_seed_outpatient($patientId, $admissionNo, $name, $icd = '')
{
    $list = DB::select(
        "SELECT id, name FROM patient_diagnoses WHERE patient_id = ? AND category = 'emergency' ORDER BY id",
        [$patientId]
    );
    if (count($list) === 0) {
        DB::insert('patient_diagnoses', [
            'patient_id' => $patientId,
            'admission_no' => $admissionNo !== '' ? $admissionNo : null,
            'category' => 'emergency',
            'icd_code' => (string)$icd,
            'name' => (string)$name,
            'note' => '',
            'sort_no' => 0,
            'created_by' => null,
            'created_by_name' => '入院登记',
            'created_at' => DateHelper::now(),
        ]);
    } elseif (count($list) === 1 && (string)$list[0]['name'] !== (string)$name) {
        DB::execute(
            "UPDATE patient_diagnoses SET name = ?, icd_code = ? WHERE id = ?",
            [(string)$name, (string)$icd, (int)$list[0]['id']]
        );
    }
    emr_diag_recompute_patient($patientId);
}

/** 是否仍有 初步/入院 诊断 (修正诊断维持约束; $excludeId 用于删除前预判) */
function emr_diag_has_initial_or_admission($patientId, $excludeId = 0)
{
    $sql = "SELECT COUNT(*) AS n FROM patient_diagnoses WHERE patient_id = ? AND category IN ('initial', 'admission')";
    $params = [$patientId];
    if ($excludeId) { $sql .= " AND id <> ?"; $params[] = $excludeId; }
    $row = DB::selectOne($sql, $params);
    return ((int)($row['n'] ?? 0)) > 0;
}
