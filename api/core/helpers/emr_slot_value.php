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
            if ($jt === 'text' && !empty($it['val'])) { $val .= (string)$it['text']; continue; }
            break;
        }
        if (in_array($jt, ['fx', 'slot', 'dyn', 'ref'], true)) break;
        if ($jt === 'text') $val .= (string)$it['text'];
    }
    return $val;
}
