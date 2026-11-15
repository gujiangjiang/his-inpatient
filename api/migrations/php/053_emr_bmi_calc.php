<?php
// 053_emr_bmi_calc.php - 内置 BMI 升级为「自动计算项」
// 存量模板 schema_json 与文书 doc_json 中的 vital_bmi_value 动态项 (无 calc 者):
// 若同文书中存在 身高(vital_height_val)/体重(vital_weight_val) 字段, 则补 calc 公式
// (BMI = 体重kg / (身高cm/100)^2, 1 位小数); 计算结果与旧版一致, 仅计算来源升级
return [
    'version' => '053',
    'description' => '内置 BMI 动态项升级为自动计算项 (模板与文书)',
    'up' => function ($m) {
        $calc = ['expr' => 'vital_weight_val / (vital_height_val / 100) ^ 2', 'dec' => 1, 'fmt' => 'num'];
        /** 递归升级正文块: 返回是否改动 */
        $walk = function (&$blocks) use (&$walk, $calc) {
            $changed = false;
            foreach ($blocks as &$b) {
                if (!is_array($b)) continue;
                if (($b['t'] ?? '') === 'p') {
                    $keys = [];
                    foreach (($b['runs'] ?? []) as $it) {
                        if (($it['t'] ?? '') === 'slot' && isset($it['key'])) $keys[(string)$it['key']] = true;
                    }
                    if (!isset($keys['vital_height_val']) || !isset($keys['vital_weight_val'])) continue;
                    foreach ($b['runs'] as &$it) {
                        if (($it['t'] ?? '') === 'dyn' && ($it['key'] ?? '') === 'vital_bmi_value' && empty($it['calc'])) {
                            $it['calc'] = $calc;
                            if (empty($it['name'])) $it['name'] = 'BMI';
                            $changed = true;
                        }
                    }
                    unset($it);
                } elseif (($b['t'] ?? '') === 'tbl') {
                    /* 注意: 不可对 ($arr ?? []) 取引用 (会对临时副本操作, 且传参报错) */
                    if (!isset($b['rows']) || !is_array($b['rows'])) continue;
                    foreach ($b['rows'] as &$r) {
                        if (!isset($r['cells']) || !is_array($r['cells'])) continue;
                        foreach ($r['cells'] as &$c) {
                            if (!isset($c['blocks']) || !is_array($c['blocks'])) continue;
                            if ($walk($c['blocks'])) $changed = true;
                        }
                        unset($c);
                    }
                    unset($r);
                }
            }
            unset($b);
            return $changed;
        };

        /* 模板 */
        foreach (DB::select("SELECT id, schema_json FROM emr_templates WHERE IFNULL(schema_json, '') <> ''") as $row) {
            $schema = json_decode((string)$row['schema_json'], true);
            if (!is_array($schema) || !is_array($schema['body'] ?? null)) continue;
            if ($walk($schema['body'])) {
                DB::execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?", [json_encode($schema, JSON_UNESCAPED_UNICODE), $row['id']]);
            }
        }
        /* 文书 (草稿; 已签名无 calc 也无妨 — 旧版 recalcBmi 兼容) */
        foreach (DB::select("SELECT id, doc_json FROM emr_documents WHERE IFNULL(doc_json, '') <> ''") as $row) {
            $doc = json_decode((string)$row['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
            if ($walk($doc['body'])) {
                DB::execute("UPDATE emr_documents SET doc_json = ? WHERE id = ?", [json_encode($doc, JSON_UNESCAPED_UNICODE), $row['id']]);
            }
        }
    }
];
