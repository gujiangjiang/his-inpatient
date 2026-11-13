<?php
// api/core/helpers/emr_guard_helper.php - 病历固定元素守卫公共校验
// emrExtractKeys: 递归提取正文固定元素键 {fx,slot} (dyn/ref 与 fx 同组; 排序去重)
// emrGuardValidate: 校验 body 键集合与 guard 完全一致; 不一致返回错误信息, 一致返回 ''
// 供 document_save (保存) 与 version_restore (恢复历史) 共用
function emrExtractKeys($body)
{
    $keys = ['fx' => [], 'slot' => []];
    $walk = function ($blocks) use (&$walk, &$keys) {
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            if (($b['t'] ?? null) === 'p') {
                foreach (($b['runs'] ?? []) as $it) {
                    if ((($it['t'] ?? null) === 'fx' || ($it['t'] ?? null) === 'dyn' || ($it['t'] ?? null) === 'ref') && isset($it['key'])) $keys['fx'][] = $it['key'];
                    if (($it['t'] ?? null) === 'slot' && isset($it['key'])) $keys['slot'][] = $it['key'];
                }
            } elseif (($b['t'] ?? null) === 'tbl') {
                foreach (($b['rows'] ?? []) as $r) foreach (($r['cells'] ?? []) as $c) $walk($c['blocks'] ?? []);
            }
        }
    };
    $walk($body);
    $keys['fx'] = array_values(array_unique($keys['fx']));
    $keys['slot'] = array_values(array_unique($keys['slot']));
    sort($keys['fx']);
    sort($keys['slot']);
    return $keys;
}

/** 校验正文键集合与固化守卫一致; 不一致返回错误信息, 一致返回 '' */
function emrGuardValidate($body, $guard)
{
    if (!is_array($guard)) return '';
    $now = emrExtractKeys($body);
    foreach (['fx', 'slot'] as $kind) {
        $want = $guard[$kind] ?? [];
        sort($want);
        if ($now[$kind] !== $want) {
            return '固定元素被改动, 禁止保存 (不可增删/篡改固定标签或填写槽)';
        }
    }
    return '';
}