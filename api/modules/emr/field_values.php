<?php
// api/modules/emr/field_values.php - 患者病历字段值汇总
// 供「字段引用」自动调用: 如病程记录模板中的【姓名】【年龄】【主诉】【专科检查】等
// 汇总该患者全部文书中的槽位值(slot)与动态只读项(dyn)/引用(ref)文本; 后者(按更新时间晚者)覆盖前者; 空值不覆盖
Auth::requireRole(['admin', 'doctor', 'nurse']);
$patientId = (int)Validator::get('patient_id', 0);
if (!$patientId) Response::error('缺少 patient_id');
if (!can_access_patient(Auth::user(), $patientId)) Response::error('无权查看该患者', 403);

$fields = [];
$docs = DB::select(
    "SELECT id, doc_json FROM emr_documents WHERE patient_id = ? ORDER BY updated_at ASC, id ASC",
    [$patientId]
);
/** 递归提取段落字段 (含表格单元格内段落) */
$extract = function ($blocks) use (&$extract, &$fields) {
    foreach ($blocks as $b) {
        if (!is_array($b)) continue;
        if (($b['t'] ?? '') === 'p') {
            $runs = $b['runs'] ?? [];
            $n = count($runs);
            for ($i = 0; $i < $n; $i++) {
                $it = $runs[$i];
                $t = $it['t'] ?? '';
                if ($t === 'dyn' || $t === 'ref') {
                    $val = trim((string)($it['text'] ?? ''));
                    if (isset($it['key']) && $val !== '') $fields[$it['key']] = $val;
                } elseif ($t === 'slot' && isset($it['key'])) {
                    /* 槽位值 = 其后到下一个保护项的文本 */
                    $val = '';
                    for ($j = $i + 1; $j < $n; $j++) {
                        $jt = $runs[$j]['t'] ?? '';
                        if (in_array($jt, ['fx', 'slot', 'dyn', 'ref'], true)) break;
                        if ($jt === 'text') $val .= (string)$runs[$j]['text'];
                    }
                    $val = trim($val);
                    if ($val !== '') $fields[$it['key']] = $val;
                }
            }
        } elseif (($b['t'] ?? '') === 'tbl') {
            foreach (($b['rows'] ?? []) as $r) {
                foreach (($r['cells'] ?? []) as $c) {
                    $extract($c['blocks'] ?? []);
                }
            }
        }
    }
};
foreach ($docs as $d) {
    if (empty($d['doc_json'])) continue;
    $doc = json_decode($d['doc_json'], true);
    if (!is_array($doc) || !is_array($doc['body'] ?? null)) continue;
    $extract($doc['body']);
}
Response::success(['fields' => $fields]);