<?php
// api/migrations/php/041_emr_info_table_layout.php - 患者信息表版式归一 (正中分割/左右两列/单行)
// 既有文书中的无边框信息表统一列宽 [46,204,76,174] 并居中 (与模板定义一致)
return [
    'version' => '041',
    'description' => '患者信息表版式归一: 正中为分割线, 左右两列 46/204/76/174, 居中',
    'up' => function($m) {
        $grid = [46, 204, 76, 174];

        /** 归一单个信息表 (无边框表): 列宽/居中; 返回是否有改动 */
        $fix = function (&$tbl) use ($grid) {
            if (!is_array($tbl) || ($tbl['t'] ?? '') !== 'tbl') return false;
            if (empty($tbl['tblPr']['plain'])) return false;
            $tbl['tblPr']['center'] = true;
            $tbl['grid'] = $grid;
            foreach (($tbl['rows'] ?? []) as &$r) {
                $i = 0;
                foreach (($r['cells'] ?? []) as &$c) {
                    if (!is_array($c)) continue;
                    if (!isset($c['tcPr']) || !is_array($c['tcPr'])) $c['tcPr'] = [];
                    $c['tcPr']['w'] = $grid[min($i, count($grid) - 1)];
                    $i++;
                }
                unset($c);
            }
            unset($r);
            return true;
        };
        $walk = function (&$blocks) use (&$walk, $fix) {
            foreach ($blocks as &$b) {
                if (($b['t'] ?? '') === 'tbl') $fix($b);
            }
            unset($b);
        };

        // 1) 文书
        foreach (DB::select("SELECT id, doc_json FROM emr_documents WHERE doc_json IS NOT NULL AND doc_json != ''") as $d) {
            $doc = json_decode($d['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
            $walk($doc['body']);
            $m->execute("UPDATE emr_documents SET doc_json = ? WHERE id = ?",
                [json_encode($doc, JSON_UNESCAPED_UNICODE), $d['id']]);
        }

        // 2) word 模板 (自定义模板同样归一)
        foreach (DB::select("SELECT id, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''") as $t) {
            $schema = json_decode($t['schema_json'], true);
            if (!is_array($schema) || ($schema['mode'] ?? '') !== 'word' || !is_array($schema['body'] ?? null)) continue;
            $walk($schema['body']);
            $m->execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?",
                [json_encode($schema, JSON_UNESCAPED_UNICODE), $t['id']]);
        }
    }
];
