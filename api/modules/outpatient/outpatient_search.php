<?php
// api/modules/outpatient/outpatient_search.php - 门诊患者/就诊检索 (门诊一体化 FHIR)
Auth::requireRole(['admin', 'doctor']);
$keyword = trim((string)Validator::get('keyword', ''));
$settings = opd_settings();
$results = [];
$source = 'live';

if ($settings['opd_enabled'] !== '1') {
    $source = 'off';
} elseif ($keyword !== '') {
    $pr = opd_fhir_request('Patient', ['name' => $keyword, '_count' => 10]);
    if ($pr['ok']) {
        foreach ($pr['entries'] as $p) {
            $pno = opd_identifier_value($p, 'urn:clinic:identifier:op');
            $enc = opd_fhir_request('Encounter', ['patient' => $p['id'], '_count' => 10, '_sort' => '-date']);
            foreach ($enc['entries'] as $e) {
                $results[] = [
                    'visit_id' => opd_identifier_value($e, 'urn:clinic:identifier:visit'),
                    'patient_no' => $pno,
                    'name' => $p['name'][0]['text'] ?? '',
                    'date' => opd_fmt_datetime($e['period']['start'] ?? ''),
                    'doctor' => '',
                    'dept' => $e['serviceProvider']['display'] ?? '',
                    'status' => $e['status'] ?? '',
                ];
            }
        }
    }
}

Response::json([
    'status' => 'success',
    'message' => '',
    'data' => $results,
    'source' => $source,
]);
