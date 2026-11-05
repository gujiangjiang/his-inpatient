<?php
// api/migrations/php/037_emr_editor.php - 病历字处理编辑器
return [
    'version' => '037',
    'description' => '病历字处理: emr_documents.doc_json + 文书版本历史表',
    'up' => function($m) {
        // 单一数据源: 完整文档 JSON (抬头/排版/正文块), 与 sections 旧结构并存
        if ($m->hasTable('emr_documents') && !$m->hasColumn('emr_documents', 'doc_json')) {
            $m->addColumn('emr_documents', 'doc_json', ['type' => 'TEXT']);
        }

        // 文书版本历史: 每次保存/签名落一版, 支持审计与回溯
        if (!$m->hasTable('emr_document_versions')) {
            $m->createTable('emr_document_versions', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'doc_id' => ['type' => 'INTEGER', 'notnull' => true],
                'doc_json' => ['type' => 'TEXT'],
                'content_html' => ['type' => 'TEXT'],
                'action' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'save'], // save|sign|restore
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'doc_id', 'references' => 'emr_documents', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
            $m->createIndex('emr_document_versions', 'idx_doc_versions_doc', ['doc_id']);
        }
    }
];
