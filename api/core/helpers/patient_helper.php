<?php
// api/core/helpers/patient_helper.php - 患者卡片派生数据

/** 护理等级内容 → 卡片等级值 */
const NURSING_LEVEL_MAP = [
    '特级护理' => 'special',
    '一级护理' => '1',
    '二级护理' => '2',
    '三级护理' => '3',
];

/**
 * 患者入科时间 (指派主管医师时间); 未入科返回 null
 */
function patient_admitted_at($patientId) {
    $row = DB::selectOne(
        "SELECT created_at FROM patient_assignments WHERE patient_id = ? AND attending_doctor_id IS NOT NULL ORDER BY id DESC LIMIT 1",
        [$patientId]
    );
    return $row ? $row['created_at'] : null;
}

/** 已用费用自动计算: 有效医嘱 (已提交及以后) 按项目库价格累计, 与首页费用口径一致 */
function patient_used_fee($patientId) {
    $fee = patient_fee_breakdown($patientId);
    return $fee['fee_total'];
}

/**
 * 批量为患者列表附加资金信息:
 *   used_fee 已产生费用 (自动累计) / balance 可用余额 / is_debt 是否欠费 (余额为负, 含可透支部分)
 */
function attach_patient_funds(array &$patients) {
    if (!$patients) return;
    $ids = [];
    foreach ($patients as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id > 0) $ids[$id] = true;
    }
    $used = [];
    if ($ids) {
        $idList = array_keys($ids);
        $ph = implode(',', array_fill(0, count($idList), '?'));
        $rows = DB::select(
            "SELECT patient_id, content, category, billing_flags FROM orders WHERE patient_id IN ($ph) AND status NOT IN ('draft', 'invalid')",
            $idList
        );
        $maps = fee_catalog_maps();
        foreach ($rows as $o) {
            if (mb_strpos((string)($o['billing_flags'] ?? ''), '不计价') !== false) continue;
            $meta = fee_order_meta($o['content'], $o['category'], $maps);
            $pid = (int)$o['patient_id'];
            $used[$pid] = ($used[$pid] ?? 0.0) + $meta['price'];
        }
    }
    foreach ($patients as &$p) {
        $pid = (int)($p['id'] ?? 0);
        $sum = round($used[$pid] ?? 0.0, 2);
        $prepay = (float)($p['prepay_fee'] ?? 0);
        $overdraft = (float)($p['overdraft_limit'] ?? 1000);
        $p['used_fee'] = $sum;
        $p['balance'] = round($prepay + $overdraft - $sum, 2);
        $p['is_debt'] = $p['balance'] < 0 ? 1 : 0;
    }
    unset($p);
}

/** 患者资金概览: 预交费用 / 已用费用(自动) / 可透支额度 / 可用余额 */
function patient_funds($patientId) {
    $p = DB::selectOne("SELECT prepay_fee, overdraft_limit FROM patients WHERE id = ?", [$patientId]);
    $prepay = $p ? (float)$p['prepay_fee'] : 0.0;
    $overdraft = $p ? (float)$p['overdraft_limit'] : 1000.0;
    $used = patient_used_fee($patientId);
    return [
        'prepay' => round($prepay, 2),
        'used' => $used,
        'overdraft' => round($overdraft, 2),
        'balance' => round($prepay + $overdraft - $used, 2),
    ];
}

/**
 * 患者是否已入科 (已指派主管医师)
 */
function is_patient_admitted($patientId) {
    return patient_admitted_at($patientId) !== null;
}

/**
 * 以最新开具的有效护理医嘱覆盖卡片护理等级
 * (未停止/未作废, 按开单顺序取最新: 如忘停一级护理又开了二级, 显示二级)
 */
function apply_latest_nursing_level(array &$patients) {
    foreach ($patients as &$p) {
        $latest = DB::selectOne(
            "SELECT content FROM orders WHERE patient_id = ? AND category != 'medication'
             AND status NOT IN ('stopped', 'invalid')
             AND content IN ('特级护理', '一级护理', '二级护理', '三级护理')
             ORDER BY id DESC LIMIT 1",
            [$p['id']]
        );
        if ($latest && isset(NURSING_LEVEL_MAP[$latest['content']])) {
            $p['nursing_level'] = NURSING_LEVEL_MAP[$latest['content']];
        }
    }
    unset($p);
    return $patients;
}
