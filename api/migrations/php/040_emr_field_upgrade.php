<?php
// api/migrations/php/040_emr_field_upgrade.php - 病历字段升级 (既有文书与模板)
// 1) vital_bmi_val 槽位 → dyn(vital_bmi_value) 动态项, 按现有身高/体重即时计算
// 2) initial_diagnosis_val 槽位 → dyn(initial_diagnosis_value) 可点击同步, 段落右对齐
// 3) treatment_plan 段: 无填写内容时整段删除 (有内容保留数据)
// 4) sign_line_val 槽位 → dyn(sign_names) 签名动态项
// 5) 文书 doc_meta.guard 按转换后正文重算 (dyn 计入 fx 组)
return [
    'version' => '040',
    'description' => '病历字段升级: BMI/初步诊断/签名动态项, 去治疗意见(空), 守卫重算',
    'up' => function($m) {
        $isProtected = function ($t) { return in_array($t, ['fx', 'slot', 'dyn'], true); };

        /** slot 后紧邻文本值 */
        $valueAfter = function (array $runs, $slotKey) {
            $on = false; $s = '';
            foreach ($runs as $it) {
                if ($on) {
                    $t = $it['t'] ?? '';
                    if (in_array($t, ['fx', 'slot', 'dyn'], true)) break;
                    if ($t === 'text') $s .= (string)$it['text'];
                } elseif (($it['t'] ?? '') === 'slot' && ($it['key'] ?? '') === $slotKey) $on = true;
            }
            return $s;
        };
        /** 槽位→动态项: 旧槽位后跟随的文本并移动态项 (值随迁移保留) */
        $convert = function (array $runs, $slotKey, $dynKey, $dynText) {
            $value = '';
            $on = false;
            foreach ($runs as $it) {
                if ($on) {
                    $t = $it['t'] ?? '';
                    if (in_array($t, ['fx', 'slot', 'dyn'], true)) break;
                    if ($t === 'text') $value .= (string)$it['text'];
                } elseif (($it['t'] ?? '') === 'slot' && ($it['key'] ?? '') === $slotKey) $on = true;
            }
            $out = []; $skip = false;
            foreach ($runs as $it) {
                $t = $it['t'] ?? '';
                if ($skip) {
                    if (in_array($t, ['fx', 'slot', 'dyn'], true)) $skip = false;
                    else continue; /* 丢弃已并入动态项的原文本 */
                }
                if ($t === 'slot' && ($it['key'] ?? '') === $slotKey) {
                    $out[] = ['t' => 'dyn', 'key' => $dynKey, 'text' => $dynText !== null ? $dynText : $value];
                    $skip = true;
                    continue;
                }
                $out[] = $it;
            }
            return $out;
        };
        $num = function ($s) {
            $v = (float)preg_replace('/[^\d.]/', '', (string)$s);
            return is_finite($v) ? $v : 0.0;
        };
        $guardOf = function ($body) {
            $g = ['fx' => [], 'slot' => []];
            foreach ($body as $b) {
                if (($b['t'] ?? '') !== 'p') continue;
                foreach (($b['runs'] ?? []) as $it) {
                    $t = $it['t'] ?? '';
                    if (($t === 'fx' || $t === 'dyn') && isset($it['key'])) $g['fx'][] = $it['key'];
                    if ($t === 'slot' && isset($it['key'])) $g['slot'][] = $it['key'];
                }
            }
            $g['fx'] = array_values(array_unique($g['fx'])); sort($g['fx']);
            $g['slot'] = array_values(array_unique($g['slot'])); sort($g['slot']);
            return $g;
        };

        /** 正文块转换 (返回新数组) */
        $transform = function (array $body) use ($valueAfter, $convert, $num) {
            $hasOld = false;
            foreach ($body as $b) {
                if (($b['t'] ?? '') !== 'p') continue;
                foreach (($b['runs'] ?? []) as $it) {
                    $k = ($it['t'] ?? '') === 'slot' ? ($it['key'] ?? '') : '';
                    if (in_array($k, ['vital_bmi_val', 'initial_diagnosis_val', 'sign_line_val'], true)) $hasOld = true;
                }
            }
            if (!$hasOld) return null; /* 无需转换 */

            $h = ''; $w = '';
            foreach ($body as $b) {
                if (($b['t'] ?? '') !== 'p') continue;
                foreach (($b['runs'] ?? []) as $it) {
                    if (($it['t'] ?? '') === 'slot' && ($it['key'] ?? '') === 'vital_height_val') $h = $valueAfter($b['runs'], 'vital_height_val');
                    if (($it['t'] ?? '') === 'slot' && ($it['key'] ?? '') === 'vital_weight_val') $w = $valueAfter($b['runs'], 'vital_weight_val');
                }
            }
            $hh = $num($h); $ww = $num($w);
            $bmi = ($hh > 0 && $ww > 0) ? number_format($ww / (($hh / 100) * ($hh / 100)), 1, '.', '') : '';

            $out = [];
            foreach ($body as $b) {
                if (($b['t'] ?? '') !== 'p') { $out[] = $b; continue; }
                $runs = $b['runs'] ?? [];
                /* 治疗意见: 无内容整段删除 */
                $isTreat = false;
                foreach ($runs as $it) if (($it['t'] ?? '') === 'fx' && ($it['key'] ?? '') === 'treatment_plan') $isTreat = true;
                if ($isTreat && trim($valueAfter($runs, 'treatment_plan_val')) === '') continue;

                $runs = $convert($runs, 'vital_bmi_val', 'vital_bmi_value', $bmi);
                $runs = $convert($runs, 'initial_diagnosis_val', 'initial_diagnosis_value', null);
                $runs = $convert($runs, 'sign_line_val', 'sign_names', null);
                $b['runs'] = $runs;
                /* 初步诊断右对齐 */
                foreach ($runs as $it) {
                    if (($it['t'] ?? '') === 'dyn' && ($it['key'] ?? '') === 'initial_diagnosis_value') {
                        $b['pPr'] = array_merge(is_array($b['pPr'] ?? null) ? $b['pPr'] : [], ['align' => 'right']);
                    }
                }
                $out[] = $b;
            }
            return $out;
        };

        // 1) word 模板
        $templates = DB::select("SELECT id, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''");
        foreach ($templates as $t) {
            $schema = json_decode($t['schema_json'], true);
            if (!is_array($schema) || ($schema['mode'] ?? '') !== 'word') continue;
            $body = $transform($schema['body'] ?? []);
            if ($body === null) continue;
            $schema['body'] = $body;
            $m->execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?",
                [json_encode($schema, JSON_UNESCAPED_UNICODE), $t['id']]);
        }

        // 2) 文书 (+guard 重算)
        $docs = DB::select("SELECT id, doc_meta, doc_json FROM emr_documents WHERE doc_json IS NOT NULL AND doc_json != ''");
        foreach ($docs as $d) {
            $doc = json_decode($d['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
            $body = $transform($doc['body']);
            if ($body === null) continue;
            $doc['body'] = $body;
            $meta = json_decode($d['doc_meta'] ?? '{}', true);
            if (!is_array($meta)) $meta = [];
            $meta['guard'] = $guardOf($body);
            $m->execute("UPDATE emr_documents SET doc_json = ?, doc_meta = ? WHERE id = ?",
                [json_encode($doc, JSON_UNESCAPED_UNICODE), json_encode($meta, JSON_UNESCAPED_UNICODE), $d['id']]);
        }
    }
];
