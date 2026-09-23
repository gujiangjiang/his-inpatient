<?php
// api/migrations/php/011_emr_structured.php - 结构化病历引擎
return [
    'version' => '011',
    'description' => '结构化病历: 分类/文书/指派/科室授权',
    'up' => function($m) {
        // 病历大类分类
        if (!$m->hasTable('emr_categories')) {
            $m->createTable('emr_categories', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'length' => '100', 'notnull' => true],
                'code' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'sort_order' => ['type' => 'INTEGER', 'default' => 0],
                'is_active' => ['type' => 'INTEGER', 'default' => 1],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true]);
        }

        // 病历文书实例 (结构化)
        if (!$m->hasTable('emr_documents')) {
            $m->createTable('emr_documents', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'category_id' => ['type' => 'INTEGER'],
                'template_id' => ['type' => 'INTEGER'],
                'title' => ['type' => 'VARCHAR', 'length' => '200'],
                'doc_meta' => ['type' => 'TEXT'],      // JSON: 文书类型/状态/创建人/签名人
                'sections' => ['type' => 'TEXT'],      // JSON: 有序段落节点集合
                'content_html' => ['type' => 'TEXT'],  // 渲染 HTML (打印/展示)
                'status' => ['type' => 'VARCHAR', 'length' => '20', 'default' => 'draft'], // draft|signed
                'created_by' => ['type' => 'INTEGER'],
                'signer_id' => ['type' => 'INTEGER'],
                'signed_at' => ['type' => 'TEXT'],
                'created_at' => ['type' => 'TEXT'],
                'updated_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'category_id', 'references' => 'emr_categories', 'on' => 'id'],
                ['column' => 'template_id', 'references' => 'emr_templates', 'on' => 'id'],
                ['column' => 'created_by', 'references' => 'users', 'on' => 'id'],
                ['column' => 'signer_id', 'references' => 'users', 'on' => 'id']
            ]]);
        }

        // 患者-医师管辖 (主管/上级)
        if (!$m->hasTable('patient_assignments')) {
            $m->createTable('patient_assignments', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'patient_id' => ['type' => 'INTEGER', 'notnull' => true],
                'admission_no' => ['type' => 'VARCHAR', 'length' => '50', 'notnull' => true],
                'attending_doctor_id' => ['type' => 'INTEGER'], // 主管(主治)医师
                'senior_doctor_id' => ['type' => 'INTEGER'],    // 上级(主任)医师
                'department_id' => ['type' => 'INTEGER'],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'patient_id', 'references' => 'patients', 'on' => 'id'],
                ['column' => 'attending_doctor_id', 'references' => 'users', 'on' => 'id'],
                ['column' => 'senior_doctor_id', 'references' => 'users', 'on' => 'id'],
                ['column' => 'department_id', 'references' => 'departments', 'on' => 'id']
            ]]);
        }

        // 医生-科室授权
        if (!$m->hasTable('doctor_department_permissions')) {
            $m->createTable('doctor_department_permissions', [
                'id' => ['type' => 'INTEGER', 'primary' => true, 'auto_increment' => true],
                'doctor_id' => ['type' => 'INTEGER', 'notnull' => true],
                'department_id' => ['type' => 'INTEGER', 'notnull' => true],
                'created_at' => ['type' => 'TEXT'],
            ], ['if_not_exists' => true, 'foreign_keys' => [
                ['column' => 'doctor_id', 'references' => 'users', 'on' => 'id'],
                ['column' => 'department_id', 'references' => 'departments', 'on' => 'id']
            ]]);
        }

        // 扩展 emr_templates: 结构化 schema + 分类
        if ($m->hasTable('emr_templates')) {
            if (!$m->hasColumn('emr_templates', 'schema_json')) {
                $m->addColumn('emr_templates', 'schema_json', ['type' => 'TEXT']);
            }
            if (!$m->hasColumn('emr_templates', 'category_id')) {
                $m->addColumn('emr_templates', 'category_id', ['type' => 'INTEGER']);
            }
        }
    }
];