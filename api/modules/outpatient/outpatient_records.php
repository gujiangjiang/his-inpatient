<?php
// api/modules/outpatient/outpatient_records.php - 患者住院信息 + 门诊记录汇总 (门诊数据来自门诊一体化)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
$patient = opd_resolve_patient($patientId);
if (!$patient) Response::error('患者不存在或无权访问', 403);

$settings = opd_settings();
$discharges = DB::select("SELECT * FROM discharge_records WHERE patient_id = ? ORDER BY discharged_at DESC", [$patientId]);

$opd = [
    'enabled' => $settings['opd_enabled'] === '1',
    'matched' => false,
    'patient_no' => '',
    'visits' => [],
    'conditions' => [],
    'prescriptions' => [],
    'error' => '',
];

if ($opd['enabled']) {
    $match = opd_fhir_find_patient($patient['id_card'] ?? '');
    if (!$match['ok']) {
        $opd['error'] = $match['error'];
    } elseif ($match['patient']) {
        $opd['matched'] = true;
        $opd['patient_no'] = opd_identifier_value($match['patient'], 'urn:clinic:identifier:op');
        $pid = $match['patient']['id'];

        // 门诊就诊 (含转归) 走门诊业务只读接口
        $visits = opd_his_read('visit_list', ['patient_no' => $opd['patient_no']]);
        foreach (($visits['data']['visits'] ?? []) as $v) {
            $opd['visits'][] = [
                'flow_no' => $v['flow_no'] ?? '',
                'dept' => $v['first_dept_name'] ?? '',
                'date' => opd_fmt_datetime($v['registered_at'] ?? ''),
                'status' => $v['status'] ?? '',
                'disposition' => $v['disposition'] ?? '',
                'disposition_detail' => $v['disposition_detail'] ?? '',
                'fee' => $v['fee'] ?? 0,
            ];
        }

        // 门诊诊断 (FHIR Condition)
        $cond = opd_fhir_request('Condition', ['patient' => $pid, '_count' => 100, '_sort' => '-date']);
        foreach ($cond['entries'] as $c) {
            $opd['conditions'][] = [
                'date' => opd_fmt_datetime($c['recordedDate'] ?? ''),
                'name' => $c['code']['text'] ?? '',
                'icd' => $c['code']['coding'][0]['code'] ?? '',
            ];
        }

        // 门诊处方 (FHIR MedicationRequest)
        $meds = opd_fhir_request('MedicationRequest', ['patient' => $pid, '_count' => 100, '_sort' => '-date']);
        foreach ($meds['entries'] as $m) {
            $opd['prescriptions'][] = [
                'date' => opd_fmt_datetime($m['authoredOn'] ?? ''),
                'name' => $m['medicationCodeableConcept']['text'] ?? '',
                'dosage' => $m['dosageInstruction'][0]['text'] ?? '',
                'qty' => $m['dispenseRequest']['quantity']['value'] ?? '',
                'status' => $m['status'] ?? '',
            ];
        }
    }
}

Response::success([
    'his' => [
        'name' => $patient['name'],
        'gender' => $patient['gender'],
        'birth_date' => $patient['birth_date'],
        'id_card' => $patient['id_card'],
        'patient_no' => $patient['patient_no'],
        'admission_no' => $patient['admission_no'],
        'admission_date' => $patient['admission_date'],
        'department' => $patient['department_name'] ?? '',
        'ward' => $patient['ward_name'] ?? '',
        'bed_no' => $patient['bed_no'] ?? '',
        'fee_type' => $patient['fee_type'] ?? '',
        'admission_diagnosis' => $patient['admission_diagnosis'] ?? '',
        'status' => $patient['status'] ?? '',
    ],
    'discharges' => $discharges,
    'opd' => $opd,
]);
