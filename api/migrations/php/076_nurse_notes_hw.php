<?php
// api/migrations/php/076_nurse_notes_hw.php - 护理记录单身高/体重列 (首次护理记录专用)
return [
    'version' => '076',
    'description' => '护理记录单补充身高/体重列 (首次护理记录时登记, 电子病历首次获取)',
    'up' => function($m) {
        if (!$m->hasTable('nursing_notes')) return;
        if (!$m->hasColumn('nursing_notes', 'height')) {
            $m->addColumn('nursing_notes', 'height', ['type' => 'REAL']);
        }
        if (!$m->hasColumn('nursing_notes', 'weight')) {
            $m->addColumn('nursing_notes', 'weight', ['type' => 'REAL']);
        }
    }
];