<?php
// api/modules/outpatient/outpatient_labs.php - 门诊检验结果查询 (门诊一体化 FHIR Observation)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
$patient = opd_resolve_patient($patientId);
if (!$patient) Response::error('患者不存在或无权访问', 403);

/** 单条检验结果拍平 (组结果按 component 展开) */
function opd_lab_flat_row($date, $group, $node, $status) {
    $value = '';
    $unit = '';
    if (isset($node['valueQuantity'])) {
        $value = $node['valueQuantity']['value'] ?? '';
        $unit = $node['valueQuantity']['unit'] ?? '';
    } elseif (isset($node['valueString'])) {
        $value = $node['valueString'];
    }
    return [
        'date' => $date,
        'group' => $group,
        'item' => $node['code']['text'] ?? '',
        'result' => (string)$value,
        'unit' => (string)$unit,
        'reference' => $node['referenceRange'][0]['text'] ?? '',
        'flag' => opd_observation_flag($node),
        'status' => $status,
    ];
}

$settings = opd_settings();
$out = [
    'enabled' => $settings['opd_enabled'] === '1',
    'matched' => false,
    'patient_no' => '',
    'rows' => [],
    'error' => '',
];

if ($out['enabled']) {
    $match = opd_fhir_find_patient($patient['id_card'] ?? '');
    if (!$match['ok']) {
        $out['error'] = $match['error'];
    } elseif ($match['patient']) {
        $out['matched'] = true;
        $out['patient_no'] = opd_identifier_value($match['patient'], 'urn:clinic:identifier:op');
        $r = opd_fhir_request('Observation', [
            'patient' => $match['patient']['id'],
            'category' => 'laboratory',
            '_count' => 200,
            '_sort' => '-date',
        ]);
        if (!$r['ok']) {
            $out['error'] = $r['error'];
        } else {
            foreach ($r['entries'] as $o) {
                $date = opd_fmt_datetime($o['effectiveDateTime'] ?? '');
                $group = $o['code']['text'] ?? '';
                $status = $o['status'] ?? '';
                if (!empty($o['component'])) {
                    foreach ($o['component'] as $c) {
                        $out['rows'][] = opd_lab_flat_row($date, $group, $c, $status);
                    }
                } else {
                    $out['rows'][] = opd_lab_flat_row($date, '', $o, $status);
                }
            }
        }
    }
}

Response::success($out);
