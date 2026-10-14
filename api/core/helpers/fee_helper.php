<?php
// api/core/helpers/fee_helper.php - 住院费用自动分类统计 (病案首页 24 项费用)
// 依据有效医嘱 (已提交及以后) 按项目库价格自动归集, 页面/打印均只读展示

/** 医嘱项目名称归一化: 去除 [基]/[国]/[省]/[谈] 等目录标签前缀 */
function fee_norm_name($name) {
    return trim(preg_replace('/^(\[[^\]]{1,6}\])+/u', '', (string)$name));
}

/** 抗菌药物识别: 目录标记优先, 名称回退 */
function fee_is_antibiotic($name, $flag) {
    if ((int)$flag === 1) return true;
    return (bool)preg_match('/头孢|青霉素|西林|沙星|霉素|硝唑|磺胺|培南|万古|克林|氟康|伐昔洛/u', (string)$name);
}

/** 项目库映射 (静态缓存): drug 药品 / lab 检验 / exam 检查 / catalog 非药品目录 */
function fee_catalog_maps() {
    static $maps = null;
    if ($maps !== null) return $maps;
    $maps = ['drug' => [], 'lab' => [], 'exam' => [], 'catalog' => []];
    if (DB::table('drug_catalog')) {
        foreach (DB::select("SELECT name, price, category FROM drug_catalog") as $r) {
            $maps['drug'][fee_norm_name($r['name'])] = [
                'price' => (float)$r['price'], 'category' => (string)($r['category'] ?? ''), 'anti' => 0];
        }
    }
    if (DB::table('medications')) {
        foreach (DB::select("SELECT name, price, is_antibiotic FROM medications") as $r) {
            $k = fee_norm_name($r['name']);
            if (!isset($maps['drug'][$k]) || (float)$maps['drug'][$k]['price'] <= 0) {
                $maps['drug'][$k] = [
                    'price' => (float)$r['price'],
                    'category' => $maps['drug'][$k]['category'] ?? '',
                    'anti' => (int)($r['is_antibiotic'] ?? 0)];
            }
        }
    }
    if (DB::table('lab_catalog')) {
        foreach (DB::select("SELECT name, price FROM lab_catalog") as $r) {
            $maps['lab'][fee_norm_name($r['name'])] = (float)$r['price'];
        }
    }
    if (DB::table('exam_catalog')) {
        foreach (DB::select("SELECT name, price, dept, category FROM exam_catalog") as $r) {
            $maps['exam'][fee_norm_name($r['name'])] = [
                'price' => (float)$r['price'], 'dept' => (string)($r['dept'] ?? ''), 'category' => (string)($r['category'] ?? '')];
        }
    }
    if (DB::table('order_catalog')) {
        foreach (DB::select("SELECT content, catalog_group FROM order_catalog") as $r) {
            $maps['catalog'][fee_norm_name($r['content'])] = ['group' => (string)($r['catalog_group'] ?? '')];
        }
    }
    return $maps;
}

/** 单条医嘱 → 价格与首页费用项 (不计价返回 price 0) */
function fee_order_meta($content, $category, array $maps) {
    $name = fee_norm_name($content);
    if ($category === 'medication') {
        $meta = $maps['drug'][$name] ?? null;
        $cat = $meta['category'] ?? '';
        $fee = $cat === '中成药' ? 'fee14' : ($cat === '中药' ? 'fee15' : 'fee13');
        $anti = $fee === 'fee13' && $meta && fee_is_antibiotic($name, $meta['anti'] ?? 0);
        return ['price' => (float)($meta['price'] ?? 0), 'fee' => $fee, 'anti' => $anti];
    }
    $price = 0.0;
    $fee = 'fee1';
    if (isset($maps['lab'][$name])) {
        $price = (float)$maps['lab'][$name];
        $fee = 'fee6';
    } elseif (isset($maps['exam'][$name])) {
        $price = (float)$maps['exam'][$name]['price'];
        $dept = $maps['exam'][$name]['dept'];
        $cat = $maps['exam'][$name]['category'];
        $fee = preg_match('/放射|超声/u', $dept) ? 'fee7' : (strpos($cat, '内镜') !== false ? 'fee9' : 'fee8');
    } else {
        $group = $maps['catalog'][$name]['group'] ?? '';
        if ($group === '护理') $fee = 'fee3';
        elseif ($group === '材料') $fee = 'fee22';
        elseif ($group === '治疗') $fee = 'fee9';
        elseif ($group === '测量' || $group === '监护') $fee = 'fee2';
        elseif ($group === '检查') $fee = preg_match('/胸片|CT|超声|磁共振|MRI|DR|X线/u', $name) ? 'fee7' : 'fee8';
        elseif ($group === '饮食' || $group === '陪护' || $group === '宣教') $fee = 'fee4';
        elseif (preg_match('/康复/u', $name)) $fee = 'fee11';
        elseif (preg_match('/中医|针灸|推拿|艾灸|贴敷|拔罐/u', $name)) $fee = 'fee12';
        elseif (preg_match('/麻醉/u', $name)) $fee = 'fee10';
        elseif (preg_match('/手术/u', $name)) $fee = 'fee10';
        elseif (preg_match('/红细胞|血浆|血小板|冷沉淀|全血|输血/u', $name)) $fee = 'fee16';
        elseif (preg_match('/白蛋白/u', $name)) $fee = 'fee17';
        elseif (preg_match('/球蛋白/u', $name)) $fee = 'fee18';
        elseif (preg_match('/凝血/u', $name)) $fee = 'fee19';
        elseif (preg_match('/细胞因子|干扰素|白介素/u', $name)) $fee = 'fee20';
    }
    if ($price <= 0 && isset($maps['drug'][$name])) $price = (float)$maps['drug'][$name]['price'];
    return ['price' => $price, 'fee' => $fee, 'anti' => false];
}

/** 病案首页住院费用: 24 项 + 子项 (费别/抗菌/物理治疗/麻醉/手术) + 总计 + 自付 */
function patient_fee_breakdown($patientId) {
    $out = [];
    for ($i = 1; $i <= 24; $i++) $out['fee' . $i] = 0.0;
    $out['fee9_phys'] = 0.0;   // (临床物理治疗费)
    $out['fee10_anes'] = 0.0;  // (麻醉费)
    $out['fee10_op'] = 0.0;    // (手术费)
    $out['fee13_anti'] = 0.0;  // (抗菌药物费用)
    $self = 0.0;

    $rows = DB::select(
        "SELECT content, category, billing_flags FROM orders WHERE patient_id = ? AND status NOT IN ('draft', 'invalid')",
        [$patientId]
    );
    $maps = fee_catalog_maps();
    foreach ($rows as $o) {
        $flags = (string)($o['billing_flags'] ?? '');
        if (mb_strpos($flags, '不计价') !== false) continue;
        $meta = fee_order_meta($o['content'], $o['category'], $maps);
        if ($meta['price'] <= 0) continue;
        $out[$meta['fee']] += $meta['price'];
        if ($meta['anti']) $out['fee13_anti'] += $meta['price'];
        if ($meta['fee'] === 'fee10') {
            $name = fee_norm_name($o['content']);
            if (mb_strpos($name, '麻醉') !== false) $out['fee10_anes'] += $meta['price'];
            else $out['fee10_op'] += $meta['price'];
        }
        if (mb_strpos($flags, '自费') !== false) $self += $meta['price'];
    }

    $total = 0.0;
    for ($i = 1; $i <= 24; $i++) {
        $out['fee' . $i] = round($out['fee' . $i], 2);
        $total += $out['fee' . $i];
    }
    foreach (['fee9_phys', 'fee10_anes', 'fee10_op', 'fee13_anti'] as $k) $out[$k] = round($out[$k], 2);
    $out['fee_total'] = round($total, 2);
    $out['fee_self'] = round($self, 2);
    return $out;
}
