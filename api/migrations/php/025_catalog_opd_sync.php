<?php
// api/migrations/php/025_catalog_opd_sync.php - 项目库门诊同步字段 (opd_id / 检验组合 / 检查描述)
return [
    'version' => '025',
    'description' => '项目库增加门诊同步字段 (opd_id / is_group / members_json / description)',
    'up' => function($m) {
        if ($m->hasTable('lab_catalog')) {
            if (!$m->hasColumn('lab_catalog', 'opd_id')) {
                $m->addColumn('lab_catalog', 'opd_id', ['type' => 'INTEGER']);
            }
            if (!$m->hasColumn('lab_catalog', 'is_group')) {
                $m->addColumn('lab_catalog', 'is_group', ['type' => 'INTEGER', 'default' => 0]);
            }
            if (!$m->hasColumn('lab_catalog', 'members_json')) {
                $m->addColumn('lab_catalog', 'members_json', ['type' => 'TEXT']);
            }
        }
        if ($m->hasTable('exam_catalog')) {
            if (!$m->hasColumn('exam_catalog', 'opd_id')) {
                $m->addColumn('exam_catalog', 'opd_id', ['type' => 'INTEGER']);
            }
            if (!$m->hasColumn('exam_catalog', 'description')) {
                $m->addColumn('exam_catalog', 'description', ['type' => 'TEXT']);
            }
        }
    }
];
