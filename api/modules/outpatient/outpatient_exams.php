<?php
// api/modules/outpatient/outpatient_exams.php - 门诊检查报告 + PACS 影像直链 (门诊一体化 FHIR)
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
$patient = opd_resolve_patient($patientId);
if (!$patient) Response::error('患者不存在或无权访问', 403);

$settings = opd_settings();
$out = [
    'enabled' => $settings['opd_enabled'] === '1',
    'matched' => false,
    'patient_no' => '',
    'pacs_enabled' => $settings['pacs_enabled'] === '1',
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
        $pid = $match['patient']['id'];

        $drs = opd_fhir_request('DiagnosticReport', ['patient' => $pid, '_count' => 100, '_sort' => '-date']);
        $studies = opd_fhir_request('ImagingStudy', ['patient' => $pid, '_count' => 200, '_sort' => '-started']);
        if (!$drs['ok']) {
            $out['error'] = $drs['error'];
        } else {
            $studyMap = [];
            foreach ($studies['entries'] as $s) {
                $studyMap[$s['id'] ?? ''] = $s;
            }
            foreach ($drs['entries'] as $d) {
                $studyRef = str_replace('ImagingStudy/', '', $d['imagingStudy'][0]['reference'] ?? '');
                $study = $studyMap[$studyRef] ?? null;
                $uid = $study ? opd_study_uid($study) : '';
                $out['rows'][] = [
                    'date' => opd_fmt_datetime($d['issued'] ?? ($d['effectiveDateTime'] ?? '')),
                    'item' => $d['code']['text'] ?? '',
                    'status' => $d['status'] ?? '',
                    'conclusion' => $d['conclusion'] ?? '',
                    'findings' => opd_extension($d, 'urn:clinic:extension:imaging-findings'),
                    'report_no' => opd_identifier_value($d, 'urn:clinic:identifier:report'),
                    'order_no' => opd_identifier_value($d, 'urn:clinic:identifier:order'),
                    'dept' => opd_extension($d, 'urn:clinic:extension:ordering-department'),
                    'doctor' => $d['resultsInterpreter'][0]['display'] ?? opd_extension($d, 'urn:clinic:extension:ordering-physician'),
                    'modality' => $study['modality'][0]['code'] ?? '',
                    'instances' => $study['numberOfInstances'] ?? '',
                    'study_uid' => $uid,
                    'viewer_url' => $uid !== '' ? opd_viewer_url($uid) : '',
                ];
            }
        }
    }
}

Response::success($out);
