<?php
// api/core/helpers/emr_calc_helper.php - 自动计算项 (dyn.calc) 公式解析与缺失校验
// 与前端 EmrCalc 同语义: 公式仅可引用当前文书内的字段代码 (slot/dyn/ref) 与内置函数
function emr_calc_funcs()
{
    /* 数值函数 + 时间差函数 (minutes/hours 取两字段原始时间值 → 分钟/小时) */
    return ['round', 'abs', 'sqrt', 'min', 'max', 'ceil', 'floor', 'minutes', 'hours'];
}

/** 公式引用的字段代码 (排除函数名); 表达式含非法字符返回 ['__INVALID__'] */
function emr_calc_expr_keys($expr)
{
    $src = is_string($expr) ? $expr : '';
    if ($src === '') return [];
    if (!preg_match_all('/\d+(?:\.\d+)?|[A-Za-z_][A-Za-z0-9_]*|\*\*|[+\-*\/^(),\s]+/', $src, $m)) {
        return ['__INVALID__'];
    }
    if (implode('', $m[0]) !== $src) return ['__INVALID__'];
    $funcs = emr_calc_funcs();
    $keys = [];
    foreach ($m[0] as $tk) {
        if (!preg_match('/^[A-Za-z_]/', $tk) || in_array($tk, $funcs, true)) continue;
        if (!in_array($tk, $keys, true)) $keys[] = $tk;
    }
    return $keys;
}

/** 收集文档中已有变量键 (slot/dyn/ref; 含表格单元格) */
function emr_calc_collect_vars($blocks, array &$vars)
{
    foreach ($blocks as $b) {
        if (!is_array($b)) continue;
        if (($b['t'] ?? '') === 'p') {
            foreach (($b['runs'] ?? []) as $it) {
                $t = $it['t'] ?? '';
                if (in_array($t, ['slot', 'dyn', 'ref'], true) && isset($it['key'])) $vars[(string)$it['key']] = true;
            }
        } elseif (($b['t'] ?? '') === 'tbl') {
            foreach (($b['rows'] ?? []) as $r) {
                foreach (($r['cells'] ?? []) as $c) emr_calc_collect_vars($c['blocks'] ?? [], $vars);
            }
        }
    }
}

/** 缺失校验: 返回 [[名称, [缺失字段代码...]], ...] (公式引用了文档中不存在的字段) */
function emr_calc_missing(array $doc)
{
    $vars = [];
    emr_calc_collect_vars($doc['body'] ?? [], $vars);
    $missing = [];
    $walk = function ($blocks) use (&$walk, $vars, &$missing) {
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            if (($b['t'] ?? '') === 'p') {
                foreach (($b['runs'] ?? []) as $it) {
                    if (($it['t'] ?? '') !== 'dyn' || empty($it['calc']['expr'])) continue;
                    $miss = [];
                    foreach (emr_calc_expr_keys((string)$it['calc']['expr']) as $k) {
                        if ($k === '__INVALID__' || !isset($vars[$k])) $miss[] = ($k === '__INVALID__' ? '非法公式' : $k);
                    }
                    if ($miss) $missing[] = [(string)($it['name'] ?? $it['key'] ?? '自动计算项'), $miss];
                }
            } elseif (($b['t'] ?? '') === 'tbl') {
                foreach (($b['rows'] ?? []) as $r) {
                    foreach (($r['cells'] ?? []) as $c) $walk($c['blocks'] ?? []);
                }
            }
        }
    };
    $walk($doc['body'] ?? []);
    return $missing;
}
