<?php
// api/core/helpers/opd_pharmacy_helper.php - 门诊药房实时查询 + 降级缓存
// 开处方选门诊药房时实时调门诊 catalog_list(drugs)；网络失败/超时降级本地
// opd_drug_cache（上次成功快照），前端标注「离线缓存」。
// 防超发：库存取自实时接口 qty（最小单位），超阈值一律拦截。

/** 门诊药房药品实时拉取（优先远程，失败回退缓存） */
function opd_pharmacy_drugs($force = false) {
    $settings = opd_settings();
    if ($settings['opd_enabled'] !== '1') {
        return ['source' => 'none', 'list' => [], 'offline' => false, 'msg' => '门诊接口未启用'];
    }

    // 实时查（含重试一次；短超时快速失败降级缓存，避免 502 网关超时）
    $lastErr = '';
    for ($try = 0; $try < 2; $try++) {
        $r = opd_his_read('catalog_list', ['type' => 'drugs'], 6);
        if ($r['ok'] && is_array($r['data']['list'] ?? null)) {
            $list = $r['data']['list'];
            // 同步缓存（幂等 upsert）
            $now = DateHelper::now();
            foreach ($list as $it) {
                $exists = DB::selectOne("SELECT id FROM opd_drug_cache WHERE opd_id = ?", [(int)$it['id']]);
                if ($exists) {
                    DB::execute("UPDATE opd_drug_cache SET name=?, generic_name=?, category=?, vendor_short=?, package_unit=?,
                                 spec=?, form=?, single_dose=?, frequency=?, route=?, price=?, qty=?, warn_qty=?,
                                 spec_pack_qty=?, spec_pack_unit=?, allow_split=?, cached_at=? WHERE id=?",
                        [(string)$it['name'], (string)($it['generic_name'] ?? ''), (string)($it['category'] ?? ''),
                         (string)($it['vendor_short'] ?? ''), (string)($it['package_unit'] ?? ''), (string)($it['spec'] ?? ''),
                         (string)($it['form'] ?? ''), (string)($it['single_dose'] ?? ''), (string)($it['frequency'] ?? ''),
                         (string)($it['route'] ?? ''), (float)$it['price'], (int)$it['qty'], (int)($it['warn_qty'] ?? 0),
                         (int)($it['spec_pack_qty'] ?? 1), (string)($it['spec_pack_unit'] ?? ''), (int)($it['allow_split'] ?? 0),
                         $now, $exists['id']]);
                } else {
                    DB::execute("INSERT INTO opd_drug_cache (opd_id, name, generic_name, category, vendor_short, package_unit,
                                   spec, form, single_dose, frequency, route, price, qty, warn_qty, spec_pack_qty, spec_pack_unit,
                                   allow_split, is_active, cached_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)",
                        [(int)$it['id'], (string)$it['name'], (string)($it['generic_name'] ?? ''), (string)($it['category'] ?? ''),
                         (string)($it['vendor_short'] ?? ''), (string)($it['package_unit'] ?? ''), (string)($it['spec'] ?? ''),
                         (string)($it['form'] ?? ''), (string)($it['single_dose'] ?? ''), (string)($it['frequency'] ?? ''),
                         (string)($it['route'] ?? ''), (float)$it['price'], (int)$it['qty'], (int)($it['warn_qty'] ?? 0),
                         (int)($it['spec_pack_qty'] ?? 1), (string)($it['spec_pack_unit'] ?? ''), (int)($it['allow_split'] ?? 0),
                         $now]);
                }
            }
            // 下架清理：缓存中门诊已无的置停用
            if (count($list) > 0) {
                $ids = array_map(function ($x) { return (int)$x['id']; }, $list);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                DB::execute("UPDATE opd_drug_cache SET is_active = 0 WHERE opd_id NOT IN ($ph)", $ids);
            }
            return ['source' => 'opd', 'list' => $list, 'offline' => false, 'msg' => '实时（门诊）'];
        }
        $lastErr = $r['msg'];
    }

    // 实时失败 → 降级缓存
    $rows = DB::select("SELECT * FROM opd_drug_cache WHERE is_active = 1 ORDER BY category, name");
    if ($rows) {
        return ['source' => 'cache', 'list' => $rows, 'offline' => true, 'msg' => '离线缓存（实时查询失败：' . $lastErr . '）'];
    }
    return ['source' => 'none', 'list' => [], 'offline' => false, 'msg' => '门诊药房不可用：' . $lastErr];
}
