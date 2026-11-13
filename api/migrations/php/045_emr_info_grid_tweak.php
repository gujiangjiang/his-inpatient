<?php
// api/migrations/php/045_emr_info_grid_tweak.php - 患者信息表列宽微调
// 右列左移 (中间分割线左移 12pt, 病史陈述者标签不再拥挤), 右值列加宽 (住址等更宽裕)
// 旧: [46, 204, 76, 174] → 新: [46, 192, 72, 190] (总宽不变, 保持居中)
return [
    'version' => '045',
    'description' => '患者信息表列宽微调 [46,192,72,190] (右列左移+值列加宽)',
    'up' => function($m) {
        $grid = [46, 192, 72, 190];

        /** 归一单个信息表 (无边框表): 列宽; 返回是否有改动
         *  注意: 不可写 `foreach (($arr ?? []) as &$ref)` —— 对临时表达式取引用修改的是副本,
         *  赋值会静默丢失 (041/046 曾因此失效); 必须「取出→引用修改→回写」 */
        $fix = function (&$tbl) use ($grid) {
            if (!is_array($tbl) || ($tbl['t'] ?? '') !== 'tbl') return false;
            if (empty($tbl['tblPr']['plain'])) return false;
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
            return true;
        };
        $walk = function (&$blocks) use (&$walk, $fix) {
            foreach ($blocks as $bi => $b) {
                if (!is_array($b)) continue;
                if (($b['t'] ?? '') === 'tbl') $fix($b);
                $rows = $b['rows'] ?? [];
                foreach ($rows as $ri => $r) {
                    $cells = $r['cells'] ?? [];
                    foreach ($cells as $ci => $c) {
                        if (isset($c['blocks'])) $walk($c['blocks']);
                    }
                    $rows[$ri]['cells'] = $cells;
                }
                if ($rows) $b['rows'] = $rows;
                $blocks[$bi] = $b;
            }
        };

        /* 模板 schema */
        if ($m->hasTable('emr_templates') && $m->hasColumn('emr_templates', 'schema_json')) {
            $rows = DB::select("SELECT id, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''");
            foreach ($rows as $row) {
                $schema = json_decode($row['schema_json'], true);
                if (!is_array($schema) || !is_array($schema['body'] ?? null)) continue;
                $walk($schema['body']);
                DB::update('emr_templates', ['schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE)], 'id = ?', [$row['id']]);
            }
        }
        /* 既有文书 */
        if ($m->hasTable('emr_documents') && $m->hasColumn('emr_documents', 'doc_json')) {
            $rows = DB::select("SELECT id, doc_json FROM emr_documents WHERE doc_json IS NOT NULL AND doc_json != ''");
            foreach ($rows as $row) {
                $doc = json_decode($row['doc_json'], true);
                if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
                $walk($doc['body']);
                DB::update('emr_documents', ['doc_json' => json_encode($doc, JSON_UNESCAPED_UNICODE)], 'id = ?', [$row['id']]);
            }
        }
    }
];
