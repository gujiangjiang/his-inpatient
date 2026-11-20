<?php
// api/core/helpers/dual_pharmacy_helper.php - 中心/门诊双药房核心逻辑
// 流程：护士核医嘱(verified) → 护士站预摆药申请(prepared) → 中心药房摆药
//       (自动算量 + 欠费拦截 + 扣 prepay 写 charge_records + 扣库存) → 发药
//       (dispensed) → 送达(delivered) → 护士执行(执行预留接口, PDA)
// 门诊药房：开处方选门诊药房时实时调门诊 catalog_list(drugs)，失败降级缓存。
// 库存口径：medications.stock_quantity 一律最小单位整数；整盒=数量×spec_pack_qty。

/** 药品最小单位名称（无则回退包装单位） */
function dual_med_unit($med) {
    return trim((string)($med['spec_pack_unit'] ?? '')) !== ''
        ? $med['spec_pack_unit'] : (string)($med['unit'] ?? '');
}

/** 包装数（每包装最小单位数，>=1） */
function dual_med_pack_qty($med) {
    return max(1, (int)($med['spec_pack_qty'] ?? 1));
}

/** 是否可拆零 */
function dual_med_split($med) {
    return (int)($med['allow_split'] ?? 0) === 1;
}

/** 频次代码 → 每日次数（qd=1, bid=2, tid=3, qid=4, qn=1, q8h=3, q12h=2, prn=1, st=1） */
function dual_freq_times($freqCode) {
    $code = strtolower(trim((string)$freqCode));
    $map = ['qd' => 1, 'qn' => 1, 'qm' => 1, 'bid' => 2, 'tid' => 3, 'qid' => 4,
            'prn' => 1, 'st' => 1, 'qod' => 1, 'q2h' => 12, 'q4h' => 6, 'q6h' => 4,
            'q8h' => 3, 'q12h' => 2];
    if (isset($map[$code])) return $map[$code];
    if (preg_match('/^q(\d+)h$/', $code, $m)) return max(1, (int)round(24 / (int)$m[1]));
    return 1;
}

/** 医嘱持续天数：结束日-开始日，至少 1 天；无结束(长期)按 1 天 */
function dual_order_days($order) {
    $start = trim((string)($order['start_date'] ?? ''));
    $end = trim((string)($order['end_date'] ?? ''));
    if ($start === '' || $end === '') return 1;
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false || $e <= $s) return 1;
    return max(1, (int)ceil(($e - $s) / 86400));
}

/**
 * 自动算量：单次剂量倍数(剂量/基准) × 日频次 × 天数 = 最小单位数；
 * 无基准数据回退 1 包装；不可拆零药品取整盒。
 */
function dual_auto_qty($order, $med) {
    $packQty = dual_med_pack_qty($med);
    $dose = (float)preg_replace('/[^0-9.]/', '', (string)($order['dosage'] ?? ''));
    $base = (float)preg_replace('/[^0-9.]/', '', (string)($order['base_dosage'] ?? ''));
    $freq = dual_freq_times($order['freq_code'] ?? '');
    $days = dual_order_days($order);

    if ($base > 0 && $freq > 0 && $days > 0) {
        $per = max(1, (int)round($dose / $base));
        $qty = max(1, $per * $freq * $days);
    } else {
        $qty = $packQty;
    }
    if (!dual_med_split($med)) {
        $qty = (int)ceil($qty / $packQty) * $packQty;
    }
    return $qty;
}
/**
 * 摆药扣费核心：校验余额 → 扣 prepay_fee → 扣库存 → 写 charge_records →
 * 医嘱置 dispensed/charge_amount/charged_at + 写发药记录(stage=dispensed)
 * @return array [ok, msg, data]
 */
function dual_dispense_order($orderId, $userId) {
    $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) return ['ok' => false, 'msg' => '医嘱不存在'];
    if ($order['category'] !== 'medication') return ['ok' => false, 'msg' => '非药品医嘱'];
    if ($order['status'] !== 'verified' && $order['dispense_status'] !== 'prepared') {
        return ['ok' => false, 'msg' => '医嘱尚未核对，不能摆药'];
    }
    if (in_array($order['dispense_status'], ['dispensed', 'delivered'], true)) {
        return ['ok' => false, 'msg' => '该医嘱已发药，不能重复摆药'];
    }
    if ($order['pharmacy_type'] !== 'center') return ['ok' => false, 'msg' => '门诊药房医嘱请走门诊药房流程'];

    $med = null;
    if ((int)$order['drug_id'] > 0) {
        $med = DB::selectOne("SELECT * FROM medications WHERE id = ?", [$order['drug_id']]);
    }
    if (!$med) {
        $med = DB::selectOne("SELECT * FROM medications WHERE name = ? AND is_active = 1 ORDER BY id LIMIT 1", [$order['content']]);
    }
    if (!$med) return ['ok' => false, 'msg' => '未找到对应中心药房药品（' . $order['content'] . '），请先在药品库存维护'];

    $qty = dual_auto_qty($order, $med);
    $packQty = dual_med_pack_qty($med);
    if ((int)$med['stock_quantity'] < $qty) {
        return ['ok' => false, 'msg' => '库存不足（需要 ' . $qty . ' ' . dual_med_unit($med) . '，当前 ' . (int)$med['stock_quantity'] . '）'];
    }
    $pricePerMin = (float)$med['price'] / $packQty;
    $amount = round($pricePerMin * $qty, 2);

    $pdo = DB::getPDO();
    $pdo->beginTransaction();
    try {
        $funds = patient_funds((int)$order['patient_id']);
        if ($funds['balance'] < $amount) {
            $pdo->rollback();
            return ['ok' => false, 'msg' => '患者预缴金不足，摆药失败（可用余额 ¥' . number_format($funds['balance'], 2) . '，需 ¥' . number_format($amount, 2) . '，请先补交费用）'];
        }
        $aff = DB::execute("UPDATE medications SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?", [$qty, $med['id'], $qty]);
        if ($aff !== 1) { $pdo->rollback(); return ['ok' => false, 'msg' => '库存不足或并发冲突，请重试']; }
        DB::execute("UPDATE patients SET prepay_fee = prepay_fee - ? WHERE id = ?", [$amount, $order['patient_id']]);
        DB::execute("INSERT INTO charge_records (patient_id, order_id, medication_id, quantity, amount, deduct_type, operator, created_at)
                     VALUES (?,?,?,?,?,?,?,?)",
            [$order['patient_id'], $order['id'], $med['id'], $qty, $amount, 'dispense', $userId, DateHelper::now()]);
        DB::execute("UPDATE orders SET dispense_status = 'dispensed', charge_amount = ?, charged_at = ?, updated_at = ?
                     WHERE id = ? AND dispense_status IN ('none','prepared')",
            [$amount, DateHelper::now(), DateHelper::now(), $order['id']]);
        $stmt = $pdo->prepare("INSERT INTO dispensing_records (patient_id, admission_no, order_id, medication_id, quantity, amount, dispensed_by, dispensed_at, status, stage, created_at)
                               VALUES (?,?,?,?,?,?,?,?, 'dispensed', 'dispensed', ?)");
        $stmt->execute([$order['patient_id'], $order['admission_no'], $order['id'], $med['id'], $qty, $amount, $userId, DateHelper::now(), DateHelper::now()]);
        $pdo->commit();
        return ['ok' => true, 'msg' => '摆药扣费成功', 'data' => [
            'new_stock' => (int)$med['stock_quantity'] - $qty, 'amount' => $amount, 'qty' => $qty,
        ]];
    } catch (Exception $e) {
        $pdo->rollback();
        return ['ok' => false, 'msg' => '摆药失败：' . $e->getMessage()];
    }
}

/** 护士站预摆药申请（可选：医嘱置 prepared + plan_time；不扣费不扣库存） */
function dual_prepare_order($orderId, $userId, $planTime = '') {
    $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) return ['ok' => false, 'msg' => '医嘱不存在'];
    if ($order['category'] !== 'medication') return ['ok' => false, 'msg' => '非药品医嘱'];
    if ($order['status'] !== 'verified') return ['ok' => false, 'msg' => '医嘱尚未核对，不能预摆药'];
    if (in_array($order['dispense_status'], ['prepared', 'dispensed', 'delivered'], true)) {
        return ['ok' => false, 'msg' => '该医嘱已预摆/发药'];
    }
    DB::execute("UPDATE orders SET dispense_status = 'prepared', plan_time = ?, execute_batch_no = ?, updated_at = ?
                 WHERE id = ? AND dispense_status = 'none'",
        [$planTime, 'PRE' . date('YmdHis') . mt_rand(10, 99), DateHelper::now(), $order['id']]);
    return ['ok' => true, 'msg' => '已申请预摆药'];
}

/** 发药送达（药师/护士：dispensed → delivered） */
function dual_deliver_order($orderId, $userId) {
    $order = DB::selectOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) return ['ok' => false, 'msg' => '医嘱不存在'];
    if ($order['dispense_status'] !== 'dispensed') return ['ok' => false, 'msg' => '医嘱尚未发药，不能送达'];
    DB::execute("UPDATE orders SET dispense_status = 'delivered', updated_at = ? WHERE id = ? AND dispense_status = 'dispensed'",
        [DateHelper::now(), $order['id']]);
    DB::execute("UPDATE dispensing_records SET stage = 'delivered' WHERE order_id = ? AND status = 'dispensed'", [$order['id']]);
    return ['ok' => true, 'msg' => '已送达'];
}
