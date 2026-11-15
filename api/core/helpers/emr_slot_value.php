<?php
// api/core/helpers/emr_slot_value.php - 字处理文档槽位值提取 (与前端模型版本对齐)
// v2: 值 = 紧邻槽位后的 val 标记文本 (可跨 br), 遇普通正文/保护项即止 (支持「下拉框+文字」混排)
// v1 (旧模型): 值 = 槽位后到下一个保护项之间的全部文本 (兼容未迁移的存量文档)
function emr_slot_value_after(array $runs, $idx, $v2)
{
    $val = '';
    $n = count($runs);
    for ($j = $idx + 1; $j < $n; $j++) {
        $it = $runs[$j];
        $jt = $it['t'] ?? '';
        if ($v2) {
            if ($jt === 'br') continue;
            if ($jt === 'text') {
                if (!empty($it['del'])) continue; /* 留痕删除的值视为未填写 */
                if (!empty($it['val'])) { $val .= (string)$it['text']; continue; }
            }
            break;
        }
        if (in_array($jt, ['fx', 'slot', 'dyn', 'ref'], true)) break;
        if ($jt === 'text' && empty($it['del'])) $val .= (string)$it['text'];
    }
    return $val;
}

/** 字段引用文本 (混排整段): 从槽位起, 收集 值文本 + 混排说明文字 + 后续槽位值/动态项文本,
 *  遇到下一个固定标签(fx)或段尾即止 —— 如「[下拉]有高血压病病史，[下拉]有糖尿病病史」
 *  引用首槽位得到完整呈现「否有高血压病病史，否有糖尿病病史」 */
function emr_slot_reference_text(array $runs, $idx)
{
    $out = '';
    $n = count($runs);
    for ($j = $idx + 1; $j < $n; $j++) {
        $it = $runs[$j];
        $jt = $it['t'] ?? '';
        if ($jt === 'fx') break; /* 下一个字段标签: 同段多字段, 引用止于此 */
        if ($jt === 'slot') {
            /* 后续槽位的值一并纳入 (无标签分隔的混排); 跳过其值 run, 避免重复计入 */
            $k = $j + 1;
            while ($k < $n && ($runs[$k]['t'] ?? '') === 'br') $k++;
            while ($k < $n && ($runs[$k]['t'] ?? '') === 'text' && !empty($runs[$k]['val'])) {
                $out .= (string)$runs[$k]['text'];
                $k++;
            }
            $j = $k - 1; /* for 自增后从值之后继续 */
            continue;
        }
        if ($jt === 'text') { if (empty($it['del'])) $out .= (string)$it['text']; continue; }
        if ($jt === 'br') { $out .= "\n"; continue; }
        if ($jt === 'tab') { $out .= "\t"; continue; }
        if ($jt === 'dyn' || $jt === 'ref') { $out .= (string)($it['text'] ?? ''); continue; }
    }
    return $out;
}
