<?php
// api/core/helpers/order/order_state_helper.php - 医嘱状态机定义与手术作废守卫
// 注意: 两张状态机语义不同, 不可合并——
//  通用流转(order_status)允许 回退草稿/申请作废/申请停止;
//  护士确认侧(order_verify)只允许确认类跳转, 若误用通用表将放行越权跳转。

/**
 * 通用流转状态机 (order_status 用): 提交/回退/申请作废/申请停止/核对/执行/完成
 */
function order_transitions_general() {
    return [
        'draft' => ['pending'],
        'pending' => ['verified', 'draft', 'invalid_pending'],
        'invalid_pending' => ['invalid'],
        'stop_pending' => ['stopped'],
        'verified' => ['executing', 'completed', 'invalid_pending', 'stop_pending'],
        'executing' => ['completed', 'invalid_pending', 'stop_pending'],
        'completed' => [],
        'stopped' => [],
        'invalid' => []
    ];
}

/**
 * 护士确认侧状态机 (order_verify 用): 仅确认类跳转
 * (核对 / 确认作废 / 确认停止 / 执行 / 完成; 无回退与申请类目标)
 */
function order_transitions_verify() {
    return [
        'draft' => ['pending'],
        'pending' => ['verified'],
        'invalid_pending' => ['invalid'],
        'stop_pending' => ['stopped'],
        'verified' => ['executing', 'completed'],
        'executing' => ['completed'],
        'completed' => [],
        'stopped' => [],
        'invalid' => []
    ];
}

/**
 * 手术医嘱不可直接作废守卫 (order_status / order_invalidate 共用):
 * 覆盖本条与所在组的组主 (surgery 关联其一即命中); 命中时直接 Response::error。
 */
function order_guard_not_surgery($order) {
    $checkIds = [(int)$order['id']];
    if (!empty($order['parent_id'])) $checkIds[] = (int)$order['parent_id'];
    $ph = implode(',', array_fill(0, count($checkIds), '?'));
    $isSurgeryOrder = (($order['category'] ?? '') === 'surgery') || ((int)(DB::selectOne(
        "SELECT COUNT(*) AS n FROM surgeries WHERE order_id IN ($ph)", $checkIds)['n'] ?? 0) > 0);
    if ($isSurgeryOrder) {
        Response::error('手术医嘱不可直接作废：请在「手术」页面「已申请手术」列表中取消手术（取消后该医嘱自动作废）');
    }
}
