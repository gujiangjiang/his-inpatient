<?php
require_once __DIR__ . '/../../core/branding.php';
// 063_emr_dual_hospital_names.php - 模板与未签名病历同步医院主/第二名称
return [
    'version' => '063',
    'description' => '病历模板和未签名草稿增加医院第二名称抬头',
    'up' => function ($m) {
        $names = branding_names();
        foreach (DB::select("SELECT id, schema_json FROM emr_templates WHERE IFNULL(schema_json, '') <> ''") as $row) {
            $schema = json_decode((string)$row['schema_json'], true);
            if (!is_array($schema)) continue;
            $schema['header'] = is_array($schema['header'] ?? null) ? $schema['header'] : [];
            $schema['header']['hospital'] = $names['hospital_name'];
            $schema['header']['hospital2'] = $names['hospital_name2'];
            DB::execute("UPDATE emr_templates SET schema_json = ? WHERE id = ?", [
                json_encode($schema, JSON_UNESCAPED_UNICODE), $row['id']
            ]);
        }
        foreach (DB::select("SELECT id, doc_json FROM emr_documents WHERE status = 'draft' AND IFNULL(doc_json, '') <> ''") as $row) {
            $doc = json_decode((string)$row['doc_json'], true);
            if (!is_array($doc)) continue;
            $doc['header'] = is_array($doc['header'] ?? null) ? $doc['header'] : [];
            $doc['header']['hospital'] = $names['hospital_name'];
            $doc['header']['hospital2'] = $names['hospital_name2'];
            DB::execute("UPDATE emr_documents SET doc_json = ?, content_html = '' WHERE id = ?", [
                json_encode($doc, JSON_UNESCAPED_UNICODE), $row['id']
            ]);
        }
    }
];
