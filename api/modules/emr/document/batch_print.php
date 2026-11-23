<?php
// api/modules/emr/document_batch_print.php - 病历文书批量打印
// 返回选中文书的完整数据 (含 doc_json/抬头/患者信息栏) 并累加打印次数 print_count。
// 打印模式: 后端按文书所属分类名判断 (分类含「病程」→ 重排打印 buildBatch; 其余 → 原样打印 content_html)
Auth::requireRole(['admin', 'doctor']);
$ids = Validator::get('ids');
if (!$ids || !is_array($ids)) Response::error('请选择要打印的文书');
$ids = array_values(array_unique(array_map('intval', $ids)));
if (!$ids) Response::error('请选择要打印的文书');

$patientId = (int)Validator::get('patient_id', 0);

$ph = implode(',', array_fill(0, count($ids), '?'));
$rows = DB::select(
    "SELECT d.id, d.patient_id, d.category_id, d.title, d.doc_meta, d.doc_json,
            d.content_html, d.status, d.created_at, d.updated_at, d.signed_at, d.print_count,
            c.name AS category_name, c.sort_order AS category_sort, u.name AS signer_name,
            t.name AS template_name
     FROM emr_documents d
     LEFT JOIN emr_categories c ON d.category_id = c.id
     LEFT JOIN emr_templates t ON d.template_id = t.id
     LEFT JOIN users u ON d.signer_id = u.id
     WHERE d.id IN ($ph)
     ORDER BY (c.sort_order IS NULL OR c.sort_order = 0) ASC, c.sort_order ASC, c.id ASC, d.created_at ASC, d.id ASC",
    $ids
);

/* 归属校验 (无条件): 按文书实际归属逐一校验, 不可因入参省略 patient_id 而绕过 */
require_access_patients(Auth::user(), array_column($rows, 'patient_id'));

$pdo = DB::getPDO();
$now = DateHelper::now();
/* 打印计数批量累加 (原逐条 UPDATE + 逐条回读, N+1 → 2), 再一次回读自增后计数 */
$rowIds = array_map(function ($r) { return (int)$r['id']; }, $rows);
if ($rowIds) {
    $ph2 = implode(',', array_fill(0, count($rowIds), '?'));
    DB::execute("UPDATE emr_documents SET print_count = print_count + 1, updated_at = ? WHERE id IN ($ph2)",
        array_merge([$now], $rowIds));
    $counts = [];
    foreach (DB::select("SELECT id, print_count FROM emr_documents WHERE id IN ($ph2)", $rowIds) as $c) {
        $counts[(int)$c['id']] = (int)$c['print_count'];
    }
}
foreach ($rows as &$r) {
    $r['doc_json'] = !empty($r['doc_json']) ? (json_decode($r['doc_json'], true) ?: null) : null;
    $r['doc_meta'] = !empty($r['doc_meta']) ? (json_decode($r['doc_meta'], true) ?: []) : [];
    /* 打印模式: 分类名含「病程」→ 重排打印 (buildBatch); 其余 → 原样打印 (content_html 快照) */
    $r['print_mode'] = (strpos((string)$r['category_name'], '病程') !== false) ? 'repaginate' : 'raw';
    /* 返回自增后的打印次数 (前端列表同步显示) */
    $rid = (int)$r['id'];
    $r['print_count'] = isset($counts[$rid]) ? $counts[$rid] : (int)$r['print_count'] + 1;
}
unset($r);
Response::success($rows);