<?php
// api/core/helpers/pub_helper.php - 模板/内容 发布可见性过滤 SQL
// 规则: 创建人可见本人全部; 已审核全院(scope=all)全院可见; 已审核科室(scope=department)对目标科室可见;
//       管理员全量可见; reviewOnly 时仅返回待审核 (审核中心用)
function emr_pub_visibility_sql($alias, $reviewOnly = false)
{
    $user = Auth::user();
    $role = $user['role'] ?? '';
    if ($role === 'admin') {
        if ($reviewOnly) return [' AND ' . $alias . ".pub_status = 'pending'", []];
        return ['', []];
    }
    $uid = (int)$user['id'];
    $dept = $user['department_id'] ?? null;
    $sql = ' AND (' . $alias . '.created_by = ' . $uid
        . " OR (" . $alias . ".pub_status = 'approved' AND " . $alias . ".pub_scope = 'all')";
    if ($dept !== null) {
        $sql .= " OR (" . $alias . ".pub_status = 'approved' AND " . $alias . ".pub_scope = 'department' AND " . $alias . '.department_id = ' . (int)$dept . ')';
    }
    $sql .= ')';
    return [$sql, []];
}