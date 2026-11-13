<?php
// api/core/helpers/dept_helper.php - 科室归属公共解析 (模板/内容等按科室管理的资源共用)
// 语义: null = 自动 (新建取本人科室; 更新保持原值); 0 或 '0' = 全院通用 (存 NULL)
function resolve_department_id($input, $existing = null) {
    if ($input === null || $input === '') {
        if ($existing !== null) return $existing;
        $u = Auth::user();
        return ($u['department_id'] ?? null);
    }
    return ($input === 0 || $input === '0') ? null : $input;
}
