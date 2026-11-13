<?php
// api/migrations/php/046_emr_info_labels_fixed.php - 信息表标签固化为固定元素 (fx)
// 背景: 信息表左右两列标签原为普通文本, 右击无元素属性且可被删除;
//       固化为 fx 后: 右击可改属性、日常删除键受保护 (与"主诉："等固定标签一致)。
// 范围: 仅更新模板 schema; 既有文书的 doc_meta.guard 已固化, 改动文书会导致守卫校验失败, 故不处理。
return [
    'version' => '046',
    'description' => '信息表标签文本固化为固定元素 fx (模板 schema, 键 il_标签)',
    'up' => function($m) {
        if (!$m->hasTable('emr_templates') || !$m->hasColumn('emr_templates', 'schema_json')) return;

        /** 归一单元格: 纯文本标签(以：结尾) → fx; 返回是否有改动
         *  注意: 不可对 `($arr ?? [])` 临时表达式 foreach 取引用 (改的是副本, 见 047 注释) */
        $fixCell = function (&$cell) {
            $changed = false;
            $blocks = $cell['blocks'] ?? [];
            foreach ($blocks as $bi => $blk) {
                if (($blk['t'] ?? '') !== 'p') continue;
                $runs = $blk['runs'] ?? [];
                if (count($runs) !== 1) continue;
                $it = $runs[0];
                if (($it['t'] ?? '') !== 'text') continue;
                $text = (string)($it['text'] ?? '');
                if ($text === '' || mb_substr($text, -1) !== '：') continue;
                if (preg_match('/[a-z0-9_]{4,}_val$/i', $text)) continue; /* 防止误伤占位文本 */
                $blocks[$bi]['runs'] = [['t' => 'fx', 'key' => 'il_' . $text, 'text' => $text]];
                $changed = true;
            }
            if ($changed) $cell['blocks'] = $blocks;
            return $changed;
        };
        /** 归一信息表 (plain): 仅第 0/2 列 (标签列) */
        $fixTbl = function (&$tbl) use ($fixCell) {
            if (!is_array($tbl) || ($tbl['t'] ?? '') !== 'tbl' || empty($tbl['tblPr']['plain'])) return false;
            $changed = false;
            $rows = $tbl['rows'] ?? [];
            foreach ($rows as $ri => $r) {
                $cells = $r['cells'] ?? [];
                foreach ($cells as $ci => $c) {
                    if ($ci % 2 !== 0) continue; /* 值列不动 */
                    $cc = $c;
                    if ($fixCell($cc)) { $cells[$ci] = $cc; $changed = true; }
                }
                $rows[$ri]['cells'] = $cells;
            }
            if ($changed) $tbl['rows'] = $rows;
            return $changed;
        };
        $walk = function (&$blocks) use (&$walk, $fixTbl) {
            foreach ($blocks as $bi => $b) {
                if (!is_array($b)) continue;
                if (($b['t'] ?? '') === 'tbl') $fixTbl($b);
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

        $rows = DB::select("SELECT id, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''");
        foreach ($rows as $row) {
            $schema = json_decode($row['schema_json'], true);
            if (!is_array($schema) || !is_array($schema['body'] ?? null)) continue;
            $walk($schema['body']);
            DB::update('emr_templates', ['schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE)], 'id = ?', [$row['id']]);
        }
    }
];
