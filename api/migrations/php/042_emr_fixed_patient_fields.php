<?php
// api/migrations/php/042_emr_fixed_patient_fields.php - 患者信息固化字段 (姓名/性别/年龄)
// 既有文书中姓名/性别/年龄的填写槽 (可编辑) 转为动态只读项 dyn (固化, 自动跟随病案首页), 守卫重算
return [
    'version' => '042',
    'description' => '患者信息固化: 姓名/性别/年龄 slot → dyn 只读并自动跟随首页',
    'up' => function($m) {
        $map = ['p_name_val' => 'p_name', 'p_gender_val' => 'p_gender', 'p_age_val' => 'p_age'];

        $guardOf = function ($body) {
            $g = ['fx' => [], 'slot' => []];
            foreach ($body as $b) {
                if (($b['t'] ?? '') !== 'p') continue;
                foreach (($b['runs'] ?? []) as $it) {
                    $t = $it['t'] ?? '';
                    if (($t === 'fx' || $t === 'dyn' || $t === 'ref') && isset($it['key'])) $g['fx'][] = $it['key'];
                    if ($t === 'slot' && isset($it['key'])) $g['slot'][] = $it['key'];
                }
            }
            $g['fx'] = array_values(array_unique($g['fx'])); sort($g['fx']);
            $g['slot'] = array_values(array_unique($g['slot'])); sort($g['slot']);
            return $g;
        };
        /** 槽位→动态只读项: 槽位后文本并入 dyn.text */
        $convert = function (array $runs, $slotKey, $dynKey) {
            $value = '';
            $on = false;
            foreach ($runs as $it) {
                if ($on) {
                    $t = $it['t'] ?? '';
                    if (in_array($t, ['fx', 'slot', 'dyn', 'ref'], true)) break;
                    if ($t === 'text') $value .= (string)$it['text'];
                } elseif (($it['t'] ?? '') === 'slot' && ($it['key'] ?? '') === $slotKey) $on = true;
            }
            $out = []; $skip = false;
            foreach ($runs as $it) {
                $t = $it['t'] ?? '';
                if ($skip) {
                    if (in_array($t, ['fx', 'slot', 'dyn', 'ref'], true)) $skip = false;
                    else continue;
                }
                if ($t === 'slot' && ($it['key'] ?? '') === $slotKey) {
                    $out[] = ['t' => 'dyn', 'key' => $dynKey, 'text' => $value];
                    $skip = true;
                    continue;
                }
                $out[] = $it;
            }
            return $out;
        };
        $hasOld = function (array $body) use ($map) {
            foreach ($body as $b) {
                if (($b['t'] ?? '') !== 'p') continue;
                foreach (($b['runs'] ?? []) as $it) {
                    if (($it['t'] ?? '') === 'slot' && isset($map[$it['key'] ?? ''])) return true;
                }
            }
            return false;
        };

        foreach (DB::select("SELECT id, doc_meta, doc_json FROM emr_documents WHERE doc_json IS NOT NULL AND doc_json != ''") as $d) {
            $doc = json_decode($d['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
            if (!$hasOld($doc['body'])) continue;
            foreach ($doc['body'] as &$b) {
                if (($b['t'] ?? '') !== 'p' || !is_array($b['runs'] ?? null)) continue;
                $runs = $b['runs'];
                foreach ($map as $slotKey => $dynKey) {
                    $runs = $convert($runs, $slotKey, $dynKey);
                }
                $b['runs'] = $runs;
            }
            unset($b);
            $meta = json_decode($d['doc_meta'] ?? '{}', true);
            if (!is_array($meta)) $meta = [];
            $meta['guard'] = $guardOf($doc['body']);
            $m->execute("UPDATE emr_documents SET doc_json = ?, doc_meta = ? WHERE id = ?",
                [json_encode($doc, JSON_UNESCAPED_UNICODE), json_encode($meta, JSON_UNESCAPED_UNICODE), $d['id']]);
        }
    }
];
