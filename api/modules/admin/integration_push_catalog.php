<?php
// api/modules/admin/integration_push_catalog.php - 推送 HIS 字典到门诊一体化 (sync-catalog)
Auth::requireRole(['admin']);
$s = opd_settings();
if ($s['opd_enabled'] !== '1') Response::error('门诊接口未启用，请先在接口管理中启用并保存');

$drugs = [];
foreach (DB::select("SELECT * FROM medications ORDER BY id") as $m) {
    $drugs[] = [
        'name' => $m['name'],
        'spec' => $m['specification'] ?? '',
        'price' => (float)$m['price'],
        'package_unit' => $m['unit'] ?? '',
        'form' => $m['dosage_form'] ?? '',
    ];
}

$disposals = [];
foreach (DB::select("SELECT * FROM order_catalog WHERE is_active = 1 ORDER BY id") as $c) {
    $disposals[] = [
        'name' => $c['content'],
        'price' => 0,
        'description' => $c['catalog_group'] ?? '',
    ];
}

$labItems = [];
foreach (DB::select("SELECT DISTINCT exam_name FROM lab_reports WHERE exam_name <> '' ORDER BY exam_name") as $r) {
    $labItems[] = ['name' => $r['exam_name'], 'price' => 0];
}

$examItems = [];
foreach (DB::select("SELECT DISTINCT exam_type FROM exam_reports WHERE exam_type <> '' ORDER BY exam_type") as $r) {
    $examItems[] = ['name' => $r['exam_type'], 'price' => 0];
}

$payload = [
    'drugs' => $drugs,
    'lab_items' => $labItems,
    'exam_items' => $examItems,
    'disposal_items' => $disposals,
];
$res = opd_his_push_catalog($payload);
if (!$res['ok']) Response::error('推送失败：' . $res['msg']);

Response::success([
    'counts' => [
        'drugs' => count($drugs),
        'lab_items' => count($labItems),
        'exam_items' => count($examItems),
        'disposal_items' => count($disposals),
    ],
    'result' => $res['data'],
], '字典已推送至门诊一体化系统');
