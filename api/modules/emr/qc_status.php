<?php
// api/modules/emr/qc_status.php - 病历质控提醒 (时间类)
// 入院记录: 入院后 24h 内完成并签名, 超时未签名 → 提醒
// 首次病程记录: 入院后 8h 内完成并签名, 超时未签名 → 提醒
// 出院记录: 出院医嘱已护士核对(status=verified) 且无已签名出院记录 → 提醒
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
$patient = DB::selectOne("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) Response::error('患者不存在', 404);
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);

$catIds = [];
foreach (DB::select("SELECT id, code FROM emr_categories") as $c) $catIds[$c['code']] = (int)$c['id'];
$signedCount = function ($code) use ($patientId, $catIds) {
    $cid = $catIds[$code] ?? 0;
    if (!$cid) return 0;
    $r = DB::selectOne("SELECT COUNT(*) n FROM emr_documents WHERE patient_id = ? AND status = 'signed' AND category_id = ?", [$patientId, $cid]);
    return (int)$r['n'];
};

$items = [];
$admitTs = TimeHelper::ts($patient['admission_date']);
if ($admitTs) {
    $elapsedH = round((time() - $admitTs) / 3600, 1);
    if ($elapsedH > 24 && $signedCount('admission') === 0) {
        $items[] = ['cat' => $catIds['admission'] ?? 0, 'code' => 'admission_24h', 'hours' => $elapsedH,
            'msg' => '入院记录需在入院后 24 小时内完成并签名，当前已超过 ' . $elapsedH . ' 小时'];
    }
    if ($elapsedH > 8) {
        $n = DB::selectOne(
            "SELECT COUNT(*) n FROM emr_documents d LEFT JOIN emr_templates t ON t.id = d.template_id
             WHERE d.patient_id = ? AND d.status = 'signed' AND (t.name = '首次病程记录' OR d.title LIKE '%首次病程%')",
            [$patientId]
        );
        if ((int)$n['n'] === 0) {
            $items[] = ['cat' => $catIds['progress'] ?? 0, 'code' => 'first_progress_8h', 'hours' => $elapsedH,
                'msg' => '首次病程记录需在入院后 8 小时内完成并签名，当前已超过 ' . $elapsedH . ' 小时'];
        }
    }
}
$dord = DB::selectOne("SELECT id, content FROM orders WHERE patient_id = ? AND content IN ('今日出院','明日出院') AND status = 'verified' ORDER BY id DESC LIMIT 1", [$patientId]);
if ($dord && $signedCount('discharge') === 0) {
    $items[] = ['cat' => $catIds['discharge'] ?? 0, 'code' => 'discharge_doc',
        'msg' => '已开具「' . $dord['content'] . '」医嘱且护士审核通过，请及时完成出院记录并签名'];
}
Response::success(['items' => $items]);
