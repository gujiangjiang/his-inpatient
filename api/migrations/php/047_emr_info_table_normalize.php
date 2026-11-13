<?php
// api/migrations/php/047_emr_info_table_normalize.php - 信息表完整归一 (权威兜底)
// 背景: 041/045/046 中 `foreach (($arr ?? []) as &$ref)` 模式对临时表达式取引用,
//       实际修改的是副本 → 单元格级修改 (列宽/标签固化) 静默丢失 (仅 grid 真实引用生效)。
//       本迁移用「取出→按引用修改→回写」的安全模式, 对全部模板 schema 做幂等归一:
//         1) 无边框信息表: 居中 + 列宽 [46,192,72,190] + 单元格 tcPr.w 同步
//         2) 第 0/2 列纯文本标签 (以：结尾) 固化为 fx (键 il_标签)
//       仅处理模板 schema; 既有文书的 guard 已固化, 不改动文书。
return [
    'version' => '047',
    'description' => '信息表完整归一: 列宽同步单元格 + 标签固化为 fx (修复 041/045/046 引用副本缺陷)',
    'up' => function($m) {
        if (!$m->hasTable('emr_templates') || !$m->hasColumn('emr_templates', 'schema_json')) return;
        $grid = [46, 192, 72, 190];

        /** 按引用安全修改数组元素: fn(&$v); 使用 callable 数组包裹避免 ?? 临时表达式 */
        $fixCell = function (&$cell) use ($grid) {
            /* 1) 列宽 */
            if (!isset($cell['tcPr']) || !is_array($cell['tcPr'])) $cell['tcPr'] = [];
            /* 2) 标签固化 */
            $blocks = $cell['blocks'] ?? [];
            foreach ($blocks as $bi => $blk) {
                if (($blk['t'] ?? '') !== 'p') continue;
                $runs = $blk['runs'] ?? [];
                if (count($runs) !== 1) continue;
                $it = $runs[0];
                if (($it['t'] ?? '') !== 'text') continue;
                $text = (string)($it['text'] ?? '');
                if ($text === '' || mb_substr($text, -1) !== '：') continue;
                $blocks[$bi]['runs'] = [['t' => 'fx', 'key' => 'il_' . $text, 'text' => $text]];
            }
            $cell['blocks'] = $blocks;
        };

        $fixTbl = function (&$tbl) use ($grid, $fixCell) {
            if (!is_array($tbl) || ($tbl['t'] ?? '') !== 'tbl' || empty($tbl['tblPr']['plain'])) return;
            $tbl['tblPr']['center'] = true;
            $tbl['grid'] = $grid;
            $rows = $tbl['rows'] ?? [];
            foreach ($rows as $ri => $r) {
                $cells = $r['cells'] ?? [];
                foreach ($cells as $ci => $c) {
                    if (!is_array($c)) continue;
                    if (!isset($c['tcPr']) || !is_array($c['tcPr'])) $c['tcPr'] = [];
                    $c['tcPr']['w'] = $grid[min($ci, count($grid) - 1)];
                    if ($ci % 2 === 0) $fixCell($c); /* 标签列才固化 fx, 值列不动 */
                    $cells[$ci] = $c;
                }
                $rows[$ri]['cells'] = $cells;
            }
            $tbl['rows'] = $rows;
        };

        $walk = function (&$blocks) use (&$walk, $fixTbl) {
            foreach ($blocks as $bi => $b) {
                if (!is_array($b)) continue;
                if (($b['t'] ?? '') === 'tbl') $fixTbl($b);
                $rows = $b['rows'] ?? [];
                foreach ($rows as $ri => $r) {
                    $cells = $r['cells'] ?? [];
                    foreach ($cells as $ci => $c) {
                        if (isset($c['blocks'])) {
                            $cb = $c['blocks'];
                            $walk($cb);
                            $cells[$ci]['blocks'] = $cb;
                        }
                    }
                    $rows[$ri]['cells'] = $cells;
                }
                if ($rows) $b['rows'] = $rows;
                $blocks[$bi] = $b;
            }
        };

        $rows = DB::select("SELECT id, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''");
        foreach ($rows as $row) {
            $schema = json_decode($row['schema_json'], true);
            if (!is_array($schema) || !is_array($schema['body'] ?? null)) continue;
            $body = $schema['body'];
            $walk($body);
            $schema['body'] = $body;
            DB::update('emr_templates', ['schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE)], 'id = ?', [$row['id']]);
        }
    }
];
