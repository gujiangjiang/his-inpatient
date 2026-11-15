<?php
require_once __DIR__ . '/../../core/emr_dicts.php';
// 054_emr_global_dict_slots.php - 已知字段选项迁移为全局字典 (slot.dict)
// 民族/婚姻/职业/病史陈述者/有无等: 模板与文书 schema/doc 中的内嵌 opts 列表移除,
// 改为引用全院统一字典键 (api/core/emr_dicts.php); 字典更新即时生效, 导出 JSON 不再携带大列表
return [
    'version' => '054',
    'description' => '民族/婚姻/职业/病史陈述者/有无等选项迁移为全局字典 (模板与文书)',
    'up' => function ($m) {
        $walk = function (&$blocks) use (&$walk) {
            $changed = false;
            foreach ($blocks as &$b) {
                if (!is_array($b)) continue;
                if (($b['t'] ?? '') === 'p') {
                    foreach ($b['runs'] as &$it) {
                        if (($it['t'] ?? '') !== 'slot' || !isset($it['key'])) continue;
                        $dict = emr_dict_key_for_slot((string)$it['key']);
                        if (!$dict) continue;
                        if (($it['dict'] ?? '') === $dict && !isset($it['opts'])) continue;
                        $it['dict'] = $dict;
                        unset($it['opts']);
                        $changed = true;
                    }
                    unset($it);
                } elseif (($b['t'] ?? '') === 'tbl') {
                    if (!isset($b['rows']) || !is_array($b['rows'])) continue;
                    foreach ($b['rows'] as &$r) {
                        if (!isset($r['cells']) || !is_array($r['cells'])) continue;
                        foreach ($r['cells'] as &$c) {
                            if (!isset($c['blocks']) || !is_array($c['blocks'])) continue;
                            if ($walk($c['blocks'])) $changed = true;
                        }
                        unset($c);
                    }
                    unset($r);
                }
            }
            unset($b);
            return $changed;
        };

        foreach (DB::select("SELECT id, schema_json FROM emr_templates WHERE IFNULL(schema_json, '') <> ''") as $row) {
            $schema = json_decode((string)$row['schema_json'], true);
            if (!is_array($schema) || !is_array($schema['body'] ?? null)) continue;
            if ($walk($schema['body'])) {
                DB::execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?", [json_encode($schema, JSON_UNESCAPED_UNICODE), $row['id']]);
            }
        }
        foreach (DB::select("SELECT id, doc_json FROM emr_documents WHERE IFNULL(doc_json, '') <> ''") as $row) {
            $doc = json_decode((string)$row['doc_json'], true);
            if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
            if ($walk($doc['body'])) {
                DB::execute("UPDATE emr_documents SET doc_json = ? WHERE id = ?", [json_encode($doc, JSON_UNESCAPED_UNICODE), $row['id']]);
            }
        }
    }
];
