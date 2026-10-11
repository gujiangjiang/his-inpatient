<?php
// api/migrations/php/026_team_doctors.php - 三级医师 (主任医师 + 诊疗组)
return [
    'version' => '026',
    'description' => '患者的医师指派增加主任医师与诊疗组字段 (三级医师)',
    'up' => function($m) {
        if (!$m->hasTable('patient_assignments')) return;
        if (!$m->hasColumn('patient_assignments', 'director_doctor_id')) {
            $m->addColumn('patient_assignments', 'director_doctor_id', ['type' => 'INTEGER']);
        }
        if (!$m->hasColumn('patient_assignments', 'treatment_group')) {
            $m->addColumn('patient_assignments', 'treatment_group', ['type' => 'VARCHAR', 'length' => '100']);
        }
    }
];
