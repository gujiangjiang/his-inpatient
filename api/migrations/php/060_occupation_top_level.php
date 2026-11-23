<?php
require_once __DIR__ . '/../../core/db/dict_db.php';
// 060_occupation_top_level.php - 首页/病历职业统一为职业分类大典 2022 八个大类
return [
    'version' => '060',
    'description' => '职业字典改为八大类并归一旧首页/草稿病历职业值',
    'up' => function ($m) {
        $rows = dict_db()->query(
            "SELECT code, name, level, parent_code FROM occupations ORDER BY level, code"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byCode = [];
        foreach ($rows as $r) $byCode[(string)$r['code']] = $r;

        $top = [];
        $nameMap = [];
        foreach ($rows as $r) {
            $cur = $r;
            $guard = 0;
            while ((int)$cur['level'] > 1 && !empty($cur['parent_code']) && $guard++ < 5) {
                $parent = $byCode[(string)$cur['parent_code']] ?? null;
                if (!$parent) break;
                $cur = $parent;
            }
            if ((int)$cur['level'] === 1) {
                $top[(string)$cur['name']] = true;
                $nameMap[(string)$r['name']] = (string)$cur['name'];
            }
        }
        $professional = '专业技术人员';
        $office = '办事人员和有关人员';
        $service = '社会生产服务和生活服务人员';
        $farming = '农、林、牧、渔业生产及辅助人员';
        $manufacturing = '生产制造及有关人员';
        $military = '军队人员';
        $other = '不便分类的其他从业人员';
        foreach (['教师', '医生', '医师', '护士', '工程师', '会计', '律师'] as $v) $nameMap[$v] = $professional;
        foreach (['公务员', '办事员', '职员'] as $v) $nameMap[$v] = $office;
        foreach (['服务员', '营业员', '个体经营者'] as $v) $nameMap[$v] = $service;
        foreach (['农民', '渔民', '牧民'] as $v) $nameMap[$v] = $farming;
        foreach (['工人', '操作工'] as $v) $nameMap[$v] = $manufacturing;
        foreach (['军人', '军官', '士兵'] as $v) $nameMap[$v] = $military;
        foreach (['学生', '退休', '无业', '学龄前儿童', '儿童', '其他'] as $v) $nameMap[$v] = $other;

        $normalize = function ($value) use ($top, $nameMap, $other) {
            $value = trim((string)$value);
            if ($value === '') return '';
            if (isset($top[$value])) return $value;
            return $nameMap[$value] ?? $other;
        };

        if ($m->hasTable('case_front_page')) {
            foreach (DB::select("SELECT id, data_json FROM case_front_page WHERE IFNULL(data_json, '') <> ''") as $row) {
                $data = json_decode((string)$row['data_json'], true);
                if (!is_array($data) || empty($data['occupation'])) continue;
                $next = $normalize($data['occupation']);
                if ($next === $data['occupation']) continue;
                $data['occupation'] = $next;
                DB::execute("UPDATE case_front_page SET data_json = ? WHERE id = ?", [
                    json_encode($data, JSON_UNESCAPED_UNICODE), $row['id']
                ]);
            }
        }

        $walk = function (&$blocks) use (&$walk, $normalize) {
            $changed = false;
            foreach ($blocks as &$block) {
                if (!is_array($block)) continue;
                if (($block['t'] ?? '') === 'p' && is_array($block['runs'] ?? null)) {
                    $runs =& $block['runs'];
                    for ($i = 0; $i < count($runs); $i++) {
                        if (($runs[$i]['t'] ?? '') !== 'slot' || ($runs[$i]['key'] ?? '') !== 'p_occupation_val') continue;
                        $start = $i + 1;
                        $end = $start;
                        $value = '';
                        $rPr = null;
                        while ($end < count($runs)) {
                            $it = $runs[$end];
                            if (($it['t'] ?? '') === 'br') { $end++; continue; }
                            if (($it['t'] ?? '') === 'text' && (!isset($it['val']) || $it['val'])) {
                                if (empty($it['del'])) $value .= (string)($it['text'] ?? '');
                                if ($rPr === null && isset($it['rPr'])) $rPr = $it['rPr'];
                                $end++;
                                continue;
                            }
                            break;
                        }
                        if (trim($value) === '') continue;
                        $next = $normalize($value);
                        if ($next === $value) continue;
                        $item = ['t' => 'text', 'text' => $next, 'val' => true];
                        if ($rPr !== null) $item['rPr'] = $rPr;
                        array_splice($runs, $start, $end - $start, [$item]);
                        $changed = true;
                    }
                    unset($runs);
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

        if ($m->hasTable('emr_documents')) {
            foreach (DB::select("SELECT id, doc_json FROM emr_documents WHERE status = 'draft' AND IFNULL(doc_json, '') <> ''") as $row) {
                $doc = json_decode((string)$row['doc_json'], true);
                if (!is_array($doc) || !is_array($doc['body'] ?? null) || !$walk($doc['body'])) continue;
                DB::execute("UPDATE emr_documents SET doc_json = ?, content_html = '' WHERE id = ?", [
                    json_encode($doc, JSON_UNESCAPED_UNICODE), $row['id']
                ]);
            }
        }
    }
];
