<?php
// api/migrations/php/039_emr_narrow_margins.php - 病历页面边距统一收窄 (Word「窄」边距)
// 更新全部 word 模板与文书的 page 配置: A4 / 上40pt 下36pt 左右36pt / 页眉间隔16pt / 页脚40pt
// 保留字号/字体/行距等既有设置
return [
    'version' => '039',
    'description' => '病历字处理: 页面边距统一收窄 (上40/下36/左右36pt, 页眉间隔16pt)',
    'up' => function($m) {
        $patch = ['paper' => 'A4', 'mt' => 40, 'mb' => 36, 'ml' => 36, 'mr' => 36, 'header_h' => 16, 'footer_h' => 40];

        // 1) word 模板
        $templates = DB::select("SELECT id, schema_json FROM emr_templates WHERE schema_json IS NOT NULL AND schema_json != ''");
        foreach ($templates as $t) {
            $schema = json_decode($t['schema_json'], true);
            if (!is_array($schema) || ($schema['mode'] ?? '') !== 'word') continue;
            $schema['page'] = array_merge(is_array($schema['page'] ?? null) ? $schema['page'] : [], $patch);
            $m->execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?",
                [json_encode($schema, JSON_UNESCAPED_UNICODE), $t['id']]);
        }

        // 2) 文书
        $docs = DB::select("SELECT id, doc_json FROM emr_documents WHERE doc_json IS NOT NULL AND doc_json != ''");
        foreach ($docs as $d) {
            $doc = json_decode($d['doc_json'], true);
            if (!is_array($doc)) continue;
            $doc['page'] = array_merge(is_array($doc['page'] ?? null) ? $doc['page'] : [], $patch);
            $m->execute("UPDATE emr_documents SET doc_json = ? WHERE id = ?",
                [json_encode($doc, JSON_UNESCAPED_UNICODE), $d['id']]);
        }
    }
];
