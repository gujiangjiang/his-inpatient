<?php
// api/core/helpers/order/order_set_helper.php - 医嘱组套条目可用性校验
// 药品: medications 目录存在 + 引用快照 (名称/规格/厂家) 一致; 非药品: order_catalog 存在 (自定义文本无引用不校验)
function order_set_items_with_avail(array $items)
{
    $drugIds = [];
    $refIds = [];
    foreach ($items as $it) {
        $cat = $it['category'] ?? '';
        if ($cat === 'medication' && !empty($it['drug_id'])) $drugIds[] = (int)$it['drug_id'];
        elseif (!empty($it['ref_id'])) $refIds[] = (int)$it['ref_id'];
    }
    $drugs = [];
    if ($drugIds) {
        $ph = implode(',', array_fill(0, count($drugIds), '?'));
        foreach (DB::select("SELECT id, name, IFNULL(specification,'') AS specification, IFNULL(manufacturer,'') AS manufacturer FROM medications WHERE id IN ($ph)", $drugIds) as $d) {
            $drugs[(int)$d['id']] = $d;
        }
    }
    $catalog = [];
    if ($refIds) {
        $ph = implode(',', array_fill(0, count($refIds), '?'));
        foreach (DB::select("SELECT id, content AS name, is_active FROM order_catalog WHERE id IN ($ph)", $refIds) as $c) {
            $catalog[(int)$c['id']] = $c;
        }
    }
    foreach ($items as &$it) {
        $avail = ['ok' => true, 'reason' => '', 'detail' => ''];
        $cat = $it['category'] ?? '';
        if ($cat === 'medication' && !empty($it['drug_id'])) {
            $d = $drugs[(int)$it['drug_id']] ?? null;
            if (!$d) {
                $avail = ['ok' => false, 'reason' => '已下架', 'detail' => '药品不存在或已下架'];
            } else {
                $diffs = [];
                if (!empty($it['ref_name']) && (string)$it['ref_name'] !== (string)$d['name']) $diffs[] = '名称已变更';
                if (!empty($it['ref_spec']) && (string)$it['ref_spec'] !== (string)$d['specification']) $diffs[] = '规格已变更';
                if (!empty($it['ref_manufacturer']) && (string)$it['ref_manufacturer'] !== (string)$d['manufacturer']) $diffs[] = '厂家已变更';
                if ($diffs) {
                    $avail = ['ok' => false, 'reason' => implode('、', $diffs),
                        'detail' => '原：' . ($it['ref_name'] ?? '') . ' ' . ($it['ref_spec'] ?? '') . ' ' . ($it['ref_manufacturer'] ?? '')
                            . ' → 现：' . $d['name'] . ' ' . $d['specification'] . ' ' . $d['manufacturer']];
                }
            }
        } elseif (!empty($it['ref_id'])) {
            $c = $catalog[(int)$it['ref_id']] ?? null;
            if (!$c || (int)$c['is_active'] !== 1) {
                $avail = ['ok' => false, 'reason' => '已停用', 'detail' => '非药品项目不存在或已停用'];
            } elseif (!empty($it['ref_name']) && (string)$it['ref_name'] !== (string)$c['name']) {
                $avail = ['ok' => false, 'reason' => '名称已变更', 'detail' => '原：' . $it['ref_name'] . ' → 现：' . $c['name']];
            }
        }
        $it['avail'] = $avail;
    }
    unset($it);
    return $items;
}
