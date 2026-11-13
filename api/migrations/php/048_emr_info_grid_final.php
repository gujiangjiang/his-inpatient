<?php
// api/migrations/php/048_emr_info_grid_final.php - 信息表列宽终版
// 病史陈述者：6 字在 12pt 下需 ≥100px 内容宽; 上一版 col3=72pt(96px) 减右侧 padding 6px 仍换行。
// 终版: [46, 188, 80, 186] (总宽 500pt 不变, 居中; col3=80pt≈107px 不再折行)
// 同样用「取出→按引用修改→回写」安全模式 (见 047 注释)。
return [
    'version' => '048',
    'description' => '信息表列宽终版 [46,188,80,186] (病史陈述者标签列加宽防折行)',
    'up' => function($m) {
        if (!$m->hasTable('emr_templates') || !$m->hasColumn('emr_templates', 'schema_json')) return;
        $grid = [46, 188, 80, 186];

        $fixTbl = function (&$tbl) use ($grid) {
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
