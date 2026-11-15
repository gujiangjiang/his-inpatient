<?php
// api/core/emr_dicts.php - 全院统一字典 (主业务库存取; 单一数据源)
// 用途: 病历模板下拉字段 (民族/婚姻/职业/病史陈述者/有无等) 引用字典键 (slot.dict),
//       模板 schema 不内嵌选项; 字典更新全院模板即时生效; 导出 JSON 干净可迁移。
// 存储: 主业务库 emr_dicts 表 (随业务备份/迁移); 首次使用自动建表并写入内置字典。
// 前端: public/js/core/emr_dicts_js.php 输出 window.EmrDicts (no-cache, 修改即时生效)。
// 后台: admin/dict-list + admin/dict-save (字典管理页)。

/** 内置字典默认值 (仅首次建表/补齐缺失键时使用) */
function emr_dict_defaults()
{
    return [
        'nation' => ['name' => '民族', 'items' => ['汉族', '蒙古族', '回族', '藏族', '维吾尔族', '苗族', '彝族', '壮族', '布依族', '朝鲜族', '满族', '侗族', '瑶族', '白族', '土家族', '其他']],
        'marriage' => ['name' => '婚姻状况', 'items' => ['未婚', '已婚', '离异', '丧偶']],
        'occupation' => ['name' => '职业', 'items' => ['工人', '农民', '学生', '教师', '职员', '军人', '自由职业', '退休', '无业', '其他']],
        'teller' => ['name' => '病史陈述者', 'items' => ['患者本人', '家属', '同事', '其他']],
        'yesno' => ['name' => '无/有', 'items' => ['无', '有']],
        'yesno_unknown' => ['name' => '无/有/不详', 'items' => ['无', '有', '不详']],
    ];
}

/** 懒创建: 表不存在则建表; 内置字典缺失则补齐 (首次使用即可用, 不依赖迁移执行) */
function emr_dict_schema_ensure()
{
    static $ensured = false;
    if ($ensured) return;
    $ensured = true;
    try {
        $exists = DB::selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='emr_dicts'");
        if (!$exists) {
            try {
                DB::getPDO()->exec("CREATE TABLE IF NOT EXISTS emr_dicts (
                    dkey VARCHAR(50) PRIMARY KEY, name VARCHAR(50) NOT NULL, items TEXT NOT NULL,
                    builtin INTEGER DEFAULT 0, updated_at TEXT, updated_by INTEGER)");
            } catch (Exception $e) { /* MySQL/PG 兜底 */ }
        }
        emr_dict_seed_defaults();
    } catch (Exception $e) { /* 库不可用时回退内置默认 (只读) */ }
}

/** 补齐内置字典 (已存在的键不覆盖 — 管理员修改保留) */
function emr_dict_seed_defaults()
{
    try {
        foreach (emr_dict_defaults() as $key => $def) {
            $row = DB::selectOne("SELECT dkey FROM emr_dicts WHERE dkey = ?", [$key]);
            if ($row) continue;
            DB::insert('emr_dicts', [
                'dkey' => $key,
                'name' => $def['name'],
                'items' => json_encode($def['items'], JSON_UNESCAPED_UNICODE),
                'builtin' => 1,
                'updated_at' => DateHelper::now(),
                'updated_by' => null,
            ]);
        }
    } catch (Exception $e) { /* ignore */ }
}

/** 全部字典: [key => items[]] (前端 window.EmrDicts 口径) */
function emr_dicts()
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $out = [];
    try {
        emr_dict_schema_ensure();
        foreach (DB::select("SELECT dkey, items FROM emr_dicts ORDER BY builtin DESC, dkey") as $row) {
            $items = json_decode((string)$row['items'], true);
            $out[(string)$row['dkey']] = is_array($items) ? array_values($items) : [];
        }
    } catch (Exception $e) {
        foreach (emr_dict_defaults() as $key => $def) $out[$key] = $def['items'];
    }
    if (!$out) {
        foreach (emr_dict_defaults() as $key => $def) $out[$key] = $def['items'];
    }
    $cache = $out;
    return $cache;
}

/** 字典元数据 (管理页): [{dkey, name, items, builtin, updated_at}] */
function emr_dict_meta()
{
    emr_dict_schema_ensure();
    $rows = DB::select("SELECT * FROM emr_dicts ORDER BY builtin DESC, dkey");
    foreach ($rows as &$r) {
        $items = json_decode((string)$r['items'], true);
        $r['items'] = is_array($items) ? array_values($items) : [];
    }
    unset($r);
    return $rows;
}

/** 保存字典 (管理页) */
function emr_dict_save($key, $name, array $items, $userId)
{
    $key = preg_replace('/[^a-z0-9_]/i', '', (string)$key);
    if ($key === '' || $name === '' || !count($items)) return false;
    emr_dict_schema_ensure();
    $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
    $exists = DB::selectOne("SELECT dkey FROM emr_dicts WHERE dkey = ?", [$key]);
    if ($exists) {
        DB::execute("UPDATE emr_dicts SET name = ?, items = ?, updated_at = ?, updated_by = ? WHERE dkey = ?", [$name, $json, DateHelper::now(), $userId, $key]);
    } else {
        DB::insert('emr_dicts', ['dkey' => $key, 'name' => $name, 'items' => $json, 'builtin' => 0, 'updated_at' => DateHelper::now(), 'updated_by' => $userId]);
    }
    return true;
}

/** 已知全局字典字段键 → 字典名 (内置模板与存量数据迁移共用) */
function emr_dict_key_for_slot($slotKey)
{
    $map = [
        'p_nation_val' => 'nation',
        'p_marriage_val' => 'marriage',
        'p_occupation_val' => 'occupation',
        'p_teller_val' => 'teller',
        'infect_dis_val' => 'yesno',
        'surgery_hx_val' => 'yesno',
        'transfusion_val' => 'yesno',
        'allergy_val' => 'yesno_unknown',
    ];
    return $map[$slotKey] ?? null;
}
