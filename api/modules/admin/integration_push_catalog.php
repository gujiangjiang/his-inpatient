<?php
// api/modules/admin/integration_push_catalog.php - 推送 HIS 字典到门诊一体化 (sync-catalog)
// 说明：HIS 药品目录属中心药房，与门诊药房（门诊一体化药品库）不是一个药房，
//       不推送、不在门诊侧建档与审核；仅推送处置项目（非药品医嘱目录）与
//       检验/检查项目名，实现字典互通（门诊侧新增项入审核中心待管理员审核）。
Auth::requireRole(['admin']);
$s = opd_settings();
if ($s['opd_enabled'] !== '1') Response::error('门诊接口未启用，请先在接口管理中启用并保存');

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
    'lab_items' => $labItems,
    'exam_items' => $examItems,
    'disposal_items' => $disposals,
];
$res = opd_his_push_catalog($payload);
if (!$res['ok']) Response::error('推送失败：' . $res['msg']);

Response::success([
    'counts' => [
        'lab_items' => count($labItems),
        'exam_items' => count($examItems),
        'disposal_items' => count($disposals),
    ],
    'result' => $res['data'],
], '字典已推送至门诊一体化系统（药品目录属中心药房，不同步）');
