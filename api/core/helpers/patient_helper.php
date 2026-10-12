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

/**
 * 已用费用自动计算: 有效医嘱 (已提交及以后) 按项目库价格累计
 * 说明: 收费标准 (数量/折扣/退费) 后续接入收费功能后完善
 */
function patient_used_fee($patientId) {
    $rows = DB::select(
        "SELECT content, category FROM orders WHERE patient_id = ? AND status NOT IN ('draft', 'invalid')",
        [$patientId]
    );
    if (!$rows) return 0.0;
    $drug = [];
    $lab = [];
    $exam = [];
    if (DB::table('drug_catalog')) {
        foreach (DB::select("SELECT name, price FROM drug_catalog") as $r) $drug[$r['name']] = (float)$r['price'];
    }
    if (DB::table('lab_catalog')) {
        foreach (DB::select("SELECT name, price FROM lab_catalog") as $r) $lab[$r['name']] = (float)$r['price'];
    }
    if (DB::table('exam_catalog')) {
        foreach (DB::select("SELECT name, price FROM exam_catalog") as $r) $exam[$r['name']] = (float)$r['price'];
    }
    $total = 0.0;
    foreach ($rows as $o) {
        $name = (string)$o['content'];
        if ($o['category'] === 'medication') {
            if (isset($drug[$name])) $total += $drug[$name];
        } else {
            if (isset($lab[$name])) $total += $lab[$name];
            elseif (isset($exam[$name])) $total += $exam[$name];
        }
    }
    return round($total, 2);
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
