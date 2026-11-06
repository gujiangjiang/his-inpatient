<?php
// api/migrations/php/038_emr_word_only.php - 病历全面字处理化 (旧 sections → word 文档模型)
// 1) emr_templates: sections 模式 schema → word 模式 (fx 固定标签 + slot 填写槽)
// 2) emr_documents: sections → doc_json (单一数据源); doc_meta.guard 固化本文书固定元素键
// 原始 sections 字段保留不动 (作为旧数据备份), 签名状态不变
return [
    'version' => '038',
    'description' => '病历全面字处理化: 旧结构化模板/文书转换 word 模型 + 文书固定元素守卫固化',
    'up' => function($m) {
        $hospital = '';
        try {
            $row = DB::selectOne("SELECT config_value FROM system_config WHERE config_key = 'hospital_name'");
            if ($row) $hospital = (string)$row['config_value'];
        } catch (Exception $e) { /* 忽略 */ }

        $page = ['paper' => 'A4', 'mt' => 62, 'mb' => 56, 'ml' => 68, 'mr' => 68,
            'header_h' => 72, 'footer_h' => 44,
            'font' => '仿宋_GB2312, FangSong, SimSun, serif', 'sz' => 12, 'line' => 1.5];
        $infoFields = [['姓名', 'name'], ['性别', 'gender'], ['科别', 'department'], ['病区', 'ward_name'],
            ['年龄', 'age'], ['床号', 'bed_no'], ['住院号', 'admission_no']];

        /** sections 数组 → word 正文块 (与前端 EmrDoc.fromSections 保持同一算法) */
        $toBody = function ($sections) {
            $body = [];
            foreach ($sections as $s) {
                if (!is_array($s) || empty($s['title'])) continue;
                $key = (isset($s['key']) && $s['key'] !== '') ? (string)$s['key'] : ('sec_' . count($body));
                $ph = (isset($s['slot']) && $s['slot'] !== '') ? (string)$s['slot'] : ('请输入' . $s['title']);
                $runs = [
                    ['t' => 'fx', 'key' => $key, 'text' => $s['title'] . '：'],
                    ['t' => 'slot', 'key' => $key . '_val', 'ph' => $ph],
                ];
                if (isset($s['content']) && $s['content'] !== '' && $s['content'] !== null) {
                    $runs[] = ['t' => 'text', 'text' => (string)$s['content']];
                }
                $body[] = ['t' => 'p', 'runs' => $runs];
            }
            if (!$body) $body[] = ['t' => 'p', 'runs' => []];
            return $body;
        };
        $guardOf = function ($body) {
            $g = ['fx' => [], 'slot' => []];
            foreach ($body as $b) {
                if (!is_array($b) || ($b['t'] ?? '') !== 'p') continue;
                foreach (($b['runs'] ?? []) as $it) {
                    $t = $it['t'] ?? '';
                    if ($t === 'fx' && isset($it['key'])) $g['fx'][] = $it['key'];
                    if ($t === 'slot' && isset($it['key'])) $g['slot'][] = $it['key'];
                }
            }
            $g['fx'] = array_values(array_unique($g['fx'])); sort($g['fx']);
            $g['slot'] = array_values(array_unique($g['slot'])); sort($g['slot']);
            return $g;
        };

        // 1) 模板: sections → word
        $templates = DB::select("SELECT id, name, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''");
        foreach ($templates as $t) {
            $schema = json_decode($t['schema_json'], true);
            if (!is_array($schema)) continue;
            if (($schema['mode'] ?? '') === 'word') continue;
            $sections = (isset($schema['sections']) && is_array($schema['sections'])) ? $schema['sections'] : [];
            if (!$sections) continue;
            $title = (isset($schema['meta']['title']) && $schema['meta']['title'] !== '') ? $schema['meta']['title'] : $t['name'];
            $word = [
                'mode' => 'word',
                'title' => $title,
                'page' => $page,
                'header' => ['hospital' => $hospital, 'doc_name' => $title, 'info_fields' => $infoFields],
                'body' => $toBody($sections),
            ];
            $m->execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?",
                [json_encode($word, JSON_UNESCAPED_UNICODE), $t['id']]);
        }

        // 2) 文书: sections → doc_json (+ doc_meta.guard)
        $docs = DB::select("SELECT id, title, doc_meta, sections FROM emr_documents WHERE (doc_json IS NULL OR doc_json = '') AND sections IS NOT NULL AND sections != '' AND sections != '[]'");
        foreach ($docs as $d) {
            $sections = json_decode($d['sections'], true);
            if (!is_array($sections) || !$sections) continue;
            $title = (string)($d['title'] ?? '');
            $body = $toBody($sections);
            $doc = [
                'v' => 1,
                'title' => $title,
                'page' => $page,
                'header' => ['hospital' => $hospital, 'doc_name' => $title, 'info_fields' => $infoFields],
                'body' => $body,
            ];
            $meta = json_decode($d['doc_meta'] ?? '{}', true);
            if (!is_array($meta)) $meta = [];
            $meta['guard'] = $guardOf($body); // 固化本文书固定元素键: 模板后续变更不影响老文书
            $m->execute("UPDATE emr_documents SET doc_json = ?, doc_meta = ? WHERE id = ?",
                [json_encode($doc, JSON_UNESCAPED_UNICODE), json_encode($meta, JSON_UNESCAPED_UNICODE), $d['id']]);
        }

        // 3) 已有 doc_json 但缺 guard 的文书: 按自身正文回填守卫 (与模板解耦)
        $docs2 = DB::select("SELECT id, doc_meta, doc_json FROM emr_documents WHERE doc_json IS NOT NULL AND doc_json != ''");
        foreach ($docs2 as $d) {
            $meta = json_decode($d['doc_meta'] ?? '{}', true);
            if (!is_array($meta)) $meta = [];
            if (isset($meta['guard']) && is_array($meta['guard'])) continue;
            $doc = json_decode($d['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
            $meta['guard'] = $guardOf($doc['body']);
            $m->execute("UPDATE emr_documents SET doc_meta = ? WHERE id = ?",
                [json_encode($meta, JSON_UNESCAPED_UNICODE), $d['id']]);
        }
    }
];
