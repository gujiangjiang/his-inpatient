<?php
// api/core/helpers/org/access_scope_helper.php - 列表数据范围过滤(护士病区/医生授权科室)
// 收敛三处同构 WHERE 片段: patients/patient_list、patients/incoming_queue、orders/order_list。
// nurse/prepare.php 的护士分支带 ward_id 非空守卫(空病区不过滤=看全部), 语义不同, 保持独立实现。

/**
 * 追加角色数据范围过滤片段, 返回 [sql, params] 供调用方拼接:
 *  - nurse: 病区等值过滤
 *  - doctor: 授权科室 IN; 无授权 → 恒假空集 (AND 1 = 0)
 *  - admin: 无附加条件
 * $wardRef/$deptRef 为列引用(如 'p.ward_id'/'p.department_id'), 仅限调用方字面量。
 * $subquery = true 时用于医嘱类表(无 ward/dept 列), $deptRef 传表别名(如 'o'),
 * 生成 {alias}.patient_id IN (SELECT id FROM patients WHERE ...) 子查询形态。
 */
function scope_where($user, $wardRef, $deptRef, $subquery = false) {
    if ($user['role'] === 'nurse') {
        if ($subquery) {
            return [" AND {$deptRef}.patient_id IN (SELECT id FROM patients WHERE ward_id = ?)", [$user['ward_id']]];
        }
        return [" AND {$wardRef} = ?", [$user['ward_id']]];
    }
    if ($user['role'] === 'doctor') {
        $allowed = allowed_department_ids($user);
        if ($allowed) {
            $ph = implode(',', array_fill(0, count($allowed), '?'));
            if ($subquery) {
                return [" AND {$deptRef}.patient_id IN (SELECT id FROM patients WHERE department_id IN ({$ph}))", $allowed];
            }
            return [" AND {$deptRef} IN ({$ph})", $allowed];
        }
        return [" AND 1 = 0", []];
    }
    return ['', []];
}
