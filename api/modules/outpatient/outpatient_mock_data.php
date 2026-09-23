<?php
// api/modules/outpatient/outpatient_mock_data.php
// 内置模拟数据供前端直接获取
$data = [
    [
        'visit_id' => 'V202609230001',
        'patient_no' => 'P001',
        'name' => '张三',
        'gender' => 'male',
        'age' => 45,
        'date' => '2026-09-15',
        'doctor' => '李医生',
        'chief_complaint' => '胸痛 3 天',
        'present_illness' => '患者 3 天前发作胸痛...',
        'physical_exam' => '心肺杂处正常...',
        'prescriptions' => [
            ['name' => '阿斯匹林', 'dosage' => '100mg', 'frequency' => 'qd'],
        ],
        'lab_results' => [
            ['name' => 'Troponin I', 'result' => '0.04', 'unit' => 'ng/mL', 'reference' => '<0.04'],
        ],
        'exam_results' => [],
    ],
];
Response::success($data);
