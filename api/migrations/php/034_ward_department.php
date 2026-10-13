<?php
// api/migrations/php/033_ward_department.php - 病区关联科室 (收住院按科室筛选病区)
return [
    'version' => '034',
    'description' => 'wards 增加 department_id (病区归属科室, 空=全部科室可用)',
    'up' => function($m) {
        if (!$m->hasTable('wards')) return;
        if (!$m->hasColumn('wards', 'department_id')) {
            $m->addColumn('wards', 'department_id', ['type' => 'INTEGER']);
        }
    }
];
