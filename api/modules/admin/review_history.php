<?php
// api/modules/admin/review_history.php - 审核中心·已处理记录（模板/内容/维护申请）
Auth::requireRole(['admin']);
$limit = max(1, min(100, (int)Validator::get('limit', 50) ?: 50));

// 病历模板已审核（approved/rejected 带审核人）
$tpls = DB::select("SELECT id, name, pub_status, pub_audited_by, pub_audited_at, pub_reject_reason
                    FROM emr_templates WHERE pub_status IN ('approved','rejected')
                    ORDER BY pub_audited_at DESC LIMIT $limit");
// 内容库已审核
$cons = DB::select("SELECT id, name, pub_status, pub_audited_by, pub_audited_at, pub_reject_reason
                    FROM emr_contents WHERE pub_status IN ('approved','rejected')
                    ORDER BY pub_audited_at DESC LIMIT $limit");
// 病历维护申请已处理
$maint = DB::select("SELECT id, doc_title, status, reviewed_by, reviewed_at, reject_reason
                     FROM emr_maintenance_requests WHERE status IN ('approved','rejected')
                     ORDER BY reviewed_at DESC LIMIT $limit");

$out = [];
foreach ($tpls as $r) {
    $out[] = ['type' => '模板', 'name' => $r['name'], 'status' => $r['pub_status'],
        'audited_by' => $r['pub_audited_by'], 'audited_at' => $r['pub_audited_at'],
        'note' => $r['pub_reject_reason']];
}
foreach ($cons as $r) {
    $out[] = ['type' => '内容库', 'name' => $r['name'], 'status' => $r['pub_status'],
        'audited_by' => $r['pub_audited_by'], 'audited_at' => $r['pub_audited_at'],
        'note' => $r['pub_reject_reason']];
}
foreach ($maint as $r) {
    $out[] = ['type' => '维护申请', 'name' => $r['doc_title'], 'status' => $r['status'],
        'audited_by' => $r['reviewed_by'], 'audited_at' => $r['reviewed_at'],
        'note' => $r['reject_reason']];
}
usort($out, function ($a, $b) { return strcmp($b['audited_at'], $a['audited_at']); });

Response::success(['list' => array_slice($out, 0, $limit)]);