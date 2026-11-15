<?php
// 052_emr_diagnosis_priority.php - 诊断优先级联动
// 口径: 修正诊断 > 入院诊断 > 初步诊断 > 门(急)诊诊断 (= 入院自带诊断)
// 1) 存量回填: 患者自带诊断(仅存在于 patients.admission_diagnosis)归入门(急)诊诊断分类
// 2) 主档展示诊断按上述优先级重算 (患者卡片/信息横栏/会诊「因xx入院」统一口径)
return [
    'version' => '052',
    'description' => '诊断优先级联动: 入院自带诊断归入门急诊分类, 主档展示按优先级重算',
    'up' => function ($m) {
        /* 1) 入院自带诊断 → 门(急)诊诊断 (仅针对尚无任何诊断记录的患者, 不重复堆积) */
        $patients = DB::select(
            "SELECT id, admission_no, admission_diagnosis, admission_icd_code FROM patients
              WHERE IFNULL(TRIM(admission_diagnosis), '') <> ''"
        );
        foreach ($patients as $p) {
            $cnt = (int)(DB::selectOne(
                "SELECT COUNT(*) AS n FROM patient_diagnoses WHERE patient_id = ?",
                [$p['id']]
            )['n'] ?? 0);
            if ($cnt > 0) continue;
            DB::insert('patient_diagnoses', [
                'patient_id' => $p['id'],
                'admission_no' => $p['admission_no'],
                'category' => 'emergency',
                'icd_code' => (string)($p['admission_icd_code'] ?? ''),
                'name' => (string)$p['admission_diagnosis'],
                'note' => '入院自带诊断（系统迁移）',
                'sort_no' => 0,
                'created_by' => null,
                'created_by_name' => '系统迁移',
                'created_at' => DateHelper::now(),
            ]);
        }

        /* 2) 主档展示诊断按优先级重算 (修正 > 入院 > 初步 > 门急诊) */
        $pids = array_column(DB::select("SELECT DISTINCT patient_id FROM patient_diagnoses"), 'patient_id');
        foreach ($pids as $pid) {
            $topRows = null;
            foreach (['corrected', 'admission', 'initial', 'emergency'] as $cat) {
                $rows = DB::select(
                    "SELECT name, IFNULL(icd_code, '') AS icd_code FROM patient_diagnoses
                      WHERE patient_id = ? AND category = ? ORDER BY sort_no, id",
                    [$pid, $cat]
                );
                if ($rows) { $topRows = $rows; break; }
            }
            if (!$topRows) continue;
            $names = array_map(function ($x) { return (string)$x['name']; }, $topRows);
            if (count($names) > 1) {
                $seq = array();
                foreach ($names as $i => $n) $seq[] = ($i + 1) . '.' . $n;
                $text = implode('；', $seq);
            } else {
                $text = implode('', $names);
            }
            $icds = array();
            foreach ($topRows as $x) {
                if ((string)$x['icd_code'] !== '') $icds[] = (string)$x['icd_code'];
            }
            DB::execute(
                "UPDATE patients SET admission_diagnosis = ?, admission_icd_code = ? WHERE id = ?",
                [$text, implode(' ', $icds), $pid]
            );
        }
    }
];
