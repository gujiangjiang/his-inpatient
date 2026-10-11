<?php
// api/core/helpers/catalog_sync.php - 项目库从门诊一体化同步 (本地缓存, opd_id 幂等)
// 门诊接口不可用时自动回退本地虚拟库; 同步节流 5 分钟

/** 同步是否到期 (节流) */
function catalog_sync_due($type, $ttl = 300) {
    $s = opd_settings();
    $at = (int)($s['catalog_sync_at_' . $type] ?? 0);
    return (time() - $at) >= $ttl;
}

/** 检查分类 → 执行科室映射 */
function exam_dept_by_category($cat) {
    $rules = [
        'CT' => '放射影像科', 'DR' => '放射影像科', 'MR' => '放射影像科', '放射' => '放射影像科',
        '超声' => '超声医学科', '心电图' => '心功能室', '内镜' => '消化内镜科', '病理' => '病理科',
    ];
    foreach ($rules as $k => $v) {
        if (mb_strpos($cat, $k) !== false) return $v;
    }
    return '医技科';
}

/** 频次中文 → 频次代码 */
function freq_code_from_text($text) {
    $map = [
        '每日一次' => 'qd', '每日1次' => 'qd', 'qd' => 'qd',
        '每日二次' => 'bid', '每日两次' => 'bid', '每日2次' => 'bid', 'bid' => 'bid',
        '每日三次' => 'tid', '每日3次' => 'tid', 'tid' => 'tid',
        '每日四次' => 'qid', '每日4次' => 'qid', 'qid' => 'qid',
        '每晚一次' => 'qn', 'qn' => 'qn', '隔日一次' => 'qod', 'qod' => 'qod',
        '必要时' => 'prn', 'prn' => 'prn', '立即' => 'st', 'st' => 'st',
    ];
    $t = trim((string)$text);
    return isset($map[$t]) ? $map[$t] : '';
}

/**
 * 同步门诊项目字典到本地缓存
 * @return array [ok, count, error] (count=-1 表示未到期跳过)
 */
function catalog_sync_from_opd($type, $force = false) {
    if (!in_array($type, ['lab_items', 'exam_items', 'drugs'], true)) {
        return ['ok' => false, 'count' => 0, 'error' => '未知字典类型'];
    }
    if (!$force && !catalog_sync_due($type)) {
        return ['ok' => true, 'count' => -1, 'error' => ''];
    }
    $r = opd_his_read('catalog_list', ['type' => $type]);
    if (!$r['ok'] || !isset($r['data']['list']) || !is_array($r['data']['list'])) {
        return ['ok' => false, 'count' => 0, 'error' => $r['msg']];
    }
    $now = DateHelper::now();
    $n = 0;
    foreach ($r['data']['list'] as $it) {
        $opdId = (int)($it['id'] ?? 0);
        if ($opdId <= 0) continue;
        if ($type === 'lab_items') {
            $fields = [
                'name' => (string)($it['name'] ?? ''),
                'category' => (string)($it['category'] ?? ''),
                'price' => (float)($it['price'] ?? 0),
                'note' => (string)($it['description'] ?? ''),
                'is_group' => (int)($it['is_group'] ?? 0),
                'members_json' => json_encode($it['members'] ?? [], JSON_UNESCAPED_UNICODE),
                'is_active' => 1,
            ];
            $exists = DB::selectOne("SELECT id FROM lab_catalog WHERE opd_id = ?", [$opdId]);
            if ($exists) {
                DB::update('lab_catalog', $fields, 'id = ?', [$exists['id']]);
            } else {
                DB::insert('lab_catalog', $fields + [
                    'opd_id' => $opdId, 'specimen' => '', 'dept' => '检验科',
                    'report_time' => '', 'sort_no' => 0, 'created_at' => $now,
                ]);
            }
        } elseif ($type === 'exam_items') {
            $cat = (string)($it['category'] ?? '');
            $fields = [
                'name' => (string)($it['name'] ?? ''),
                'category' => $cat,
                'sub_category' => '',
                'dept' => exam_dept_by_category($cat),
                'price' => (float)($it['price'] ?? 0),
                'description' => (string)($it['description'] ?? ''),
                'is_active' => 1,
            ];
            $exists = DB::selectOne("SELECT id FROM exam_catalog WHERE opd_id = ?", [$opdId]);
            if ($exists) {
                DB::update('exam_catalog', $fields, 'id = ?', [$exists['id']]);
            } else {
                DB::insert('exam_catalog', $fields + [
                    'opd_id' => $opdId, 'sort_no' => 0, 'created_at' => $now,
                ]);
            }
        } else {
            // drugs: 门诊药品字典
            $freqText = (string)($it['frequency'] ?? '');
            $fields = [
                'name' => (string)($it['name'] ?? ''),
                'spec' => (string)($it['spec'] ?? ''),
                'unit' => (string)($it['package_unit'] ?? ''),
                'price' => (float)($it['price'] ?? 0),
                'vendor' => (string)($it['vendor'] ?? ''),
                'vendor_short' => (string)($it['vendor_short'] ?? ''),
                'category' => (string)($it['category'] ?? ''),
                'form' => (string)($it['form'] ?? ''),
                'single_dose' => (string)($it['single_dose'] ?? ''),
                'frequency_code' => freq_code_from_text($freqText),
                'frequency_text' => $freqText,
                'route' => (string)($it['route'] ?? ''),
                'is_active' => 1,
            ];
            $exists = DB::selectOne("SELECT id FROM drug_catalog WHERE opd_id = ?", [$opdId]);
            if ($exists) {
                DB::update('drug_catalog', $fields, 'id = ?', [$exists['id']]);
            } else {
                DB::insert('drug_catalog', $fields + [
                    'opd_id' => $opdId, 'sort_no' => 0, 'created_at' => $now,
                ]);
            }
        }
        $n++;
    }
    opd_settings_save(['catalog_sync_at_' . $type => (string)time()]);
    return ['ok' => true, 'count' => $n, 'error' => ''];
}
