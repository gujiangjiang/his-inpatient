<?php
// 061_emr_vital_units_layout.php - 体征字段单位持久显示 + 标题/数值/单位居中分组
return [
    'version' => '061',
    'description' => '体温/脉搏/呼吸/血压/身高/体重/BMI 增加单位并改为居中紧凑布局',
    'up' => function ($m) {
        $units = [
            'vital_temp_val' => ['vital_temp_unit', '℃'],
            'vital_pulse_val' => ['vital_pulse_unit', '次/分'],
            'vital_resp_val' => ['vital_resp_unit', '次/分'],
            'vital_bp_val' => ['vital_bp_unit', 'mmHg'],
            'vital_height_val' => ['vital_height_unit', 'cm'],
            'vital_weight_val' => ['vital_weight_unit', 'kg'],
        ];
        $unitKeys = [];
        foreach ($units as $u) $unitKeys[$u[0]] = true;
        $unitKeys['vital_bmi_unit'] = true;

        $walk = function (&$blocks) use (&$walk, $units, $unitKeys) {
            $changed = false;
            foreach ($blocks as &$block) {
                if (!is_array($block)) continue;
                if (($block['t'] ?? '') === 'p' && is_array($block['runs'] ?? null)) {
                    $hasVital = false;
                    foreach ($block['runs'] as $it) {
                        if (($it['t'] ?? '') === 'slot' && isset($units[$it['key'] ?? ''])) $hasVital = true;
                        if (($it['t'] ?? '') === 'dyn' && ($it['key'] ?? '') === 'vital_bmi_value') $hasVital = true;
                    }
                    if (!$hasVital) continue;
                    $before = json_encode($block, JSON_UNESCAPED_UNICODE);
                    $block['pPr'] = is_array($block['pPr'] ?? null) ? $block['pPr'] : [];
                    $block['pPr']['flex'] = 'center';
                    $runs = $block['runs'];
                    $new = [];
                    for ($i = 0; $i < count($runs); $i++) {
                        $it = $runs[$i];
                        $type = $it['t'] ?? '';
                        $key = $it['key'] ?? '';
                        if ($type === 'slot' && isset($units[$key])) {
                            $it['ph'] = '　';
                            $new[] = $it;
                            $j = $i + 1;
                            /* 已有槽值必须位于单位之前; 顺便补齐旧文书缺失的 val 标记 */
                            while ($j < count($runs)) {
                                $v = $runs[$j];
                                if (($v['t'] ?? '') === 'br') { $new[] = $v; $j++; continue; }
                                if (($v['t'] ?? '') === 'text' && (!isset($v['val']) || $v['val'])) {
                                    $v['val'] = true;
                                    $new[] = $v;
                                    $j++;
                                    continue;
                                }
                                break;
                            }
                            list($unitKey, $text) = $units[$key];
                            if ($j < count($runs) && ($runs[$j]['t'] ?? '') === 'fx' && ($runs[$j]['key'] ?? '') === $unitKey) {
                                $unit = $runs[$j];
                                $unit['text'] = $text;
                                $new[] = $unit;
                                $j++;
                            } else {
                                $new[] = ['t' => 'fx', 'key' => $unitKey, 'text' => $text];
                            }
                            $i = $j - 1;
                            continue;
                        }
                        if ($type === 'dyn' && $key === 'vital_bmi_value') {
                            $new[] = $it;
                            $j = $i + 1;
                            if ($j < count($runs) && ($runs[$j]['t'] ?? '') === 'fx' && ($runs[$j]['key'] ?? '') === 'vital_bmi_unit') {
                                $unit = $runs[$j];
                                $unit['text'] = 'kg/m²';
                                $new[] = $unit;
                                $i = $j;
                            } else {
                                $new[] = ['t' => 'fx', 'key' => 'vital_bmi_unit', 'text' => 'kg/m²'];
                            }
                            continue;
                        }
                        if ($type === 'fx' && isset($unitKeys[$key])) continue; /* 清理重复/游离单位 */
                        $new[] = $it;
                    }
                    $block['runs'] = $new;
                    if ($before !== json_encode($block, JSON_UNESCAPED_UNICODE)) $changed = true;
                } elseif (($block['t'] ?? '') === 'tbl' && is_array($block['rows'] ?? null)) {
                    foreach ($block['rows'] as &$r) {
                        if (!is_array($r['cells'] ?? null)) continue;
                        foreach ($r['cells'] as &$c) {
                            if (is_array($c['blocks'] ?? null) && $walk($c['blocks'])) $changed = true;
                        }
                        unset($c);
                    }
                    unset($r);
                }
            }
            unset($block);
            return $changed;
        };

        $guardOf = function ($blocks) {
            $guard = ['fx' => [], 'slot' => []];
            $scan = function ($list) use (&$scan, &$guard) {
                foreach ($list as $b) {
                    if (($b['t'] ?? '') === 'p') {
                        foreach (($b['runs'] ?? []) as $it) {
                            $type = $it['t'] ?? '';
                            $key = $it['key'] ?? '';
                            if ($key === '') continue;
                            if ($type === 'slot') $guard['slot'][] = $key;
                            elseif (in_array($type, ['fx', 'dyn', 'ref'], true)) $guard['fx'][] = $key;
                        }
                    } elseif (($b['t'] ?? '') === 'tbl') {
                        foreach (($b['rows'] ?? []) as $r) foreach (($r['cells'] ?? []) as $c) $scan($c['blocks'] ?? []);
                    }
                }
            };
            $scan($blocks);
            $guard['fx'] = array_values(array_unique($guard['fx']));
            $guard['slot'] = array_values(array_unique($guard['slot']));
            sort($guard['fx']);
            sort($guard['slot']);
            return $guard;
        };

        foreach (DB::select("SELECT id, schema_json FROM emr_templates WHERE IFNULL(schema_json, '') <> ''") as $row) {
            $schema = json_decode((string)$row['schema_json'], true);
            if (!is_array($schema) || !is_array($schema['body'] ?? null) || !$walk($schema['body'])) continue;
            DB::execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?", [
                json_encode($schema, JSON_UNESCAPED_UNICODE), $row['id']
            ]);
        }
        foreach (DB::select("SELECT id, doc_json, doc_meta FROM emr_documents WHERE status = 'draft' AND IFNULL(doc_json, '') <> ''") as $row) {
            $doc = json_decode((string)$row['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null) || !$walk($doc['body'])) continue;
            $meta = json_decode((string)($row['doc_meta'] ?? ''), true);
            if (!is_array($meta)) $meta = [];
            $meta['guard'] = $guardOf($doc['body']);
            DB::execute("UPDATE emr_documents SET doc_json = ?, doc_meta = ?, content_html = '' WHERE id = ?", [
                json_encode($doc, JSON_UNESCAPED_UNICODE), json_encode($meta, JSON_UNESCAPED_UNICODE), $row['id']
            ]);
        }
    }
];
