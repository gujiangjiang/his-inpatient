<?php
// api/migrations/php/044_emr_contents.php - 病历内容库 (纯文本条目, 供写病历引用)
// 内容 = 纯文本片段库 (如"锁骨骨折查体"), 与带格式的模板互补; 按分类组织
return [
    'version' => '044',
    'description' => '新增 emr_contents 内容库表 (名称/分类/正文)',
    'up' => function($m) {
        if (!$m->hasTable('emr_contents')) {
            $m->createTable('emr_contents', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'category_id' => ['type' => 'INTEGER'],
                'content' => ['type' => 'TEXT'],
                'created_by' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
                'updated_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id']
            ]]);
            $m->createIndex('emr_contents', 'idx_emr_contents_cat', ['category_id']);
        }
    }
];
