<?php
// api/core/emr_dicts.php - 全院统一字典 (独立字典库 data/dicts.sqlite, 见 dict_db.php)
// 用途: 病历模板下拉字段 (民族/婚姻/职业/病史陈述者/有无等) 引用字典键 (slot.dict),
//       模板 schema 不内嵌选项; 字典更新全院模板即时生效。
// 存储: 独立字典库 dicts 表 (与主业务库/配置库分离; 数据体量大且静态);
//       首次使用自动建库导入种子 (api/core/dict_data/dicts.json, 不写入 PHP)。
// 前端: public/js/core/emr_dicts_js.php 输出 window.EmrDicts (no-cache, 修改即时生效)。

require_once __DIR__ . '/dict_db.php';

/** 内置字典默认值 (仅作库不可用时的只读回退; 正式数据来自 dicts.sqlite) */
function emr_dict_defaults()
{
    $rows = dict_seed_json('dicts');
    $out = [];
    foreach (($rows ?: []) as $d) $out[$d['key']] = ['name' => $d['name'], 'items' => $d['items']];
    return $out;
}

/** 兼容旧接口: 懒建库导入 (dict_db_ensure 幂等) */
function emr_dict_schema_ensure()
{
    dict_db_ensure();
}

/** 兼容旧接口: 补齐内置字典 (不覆盖已存在键) */
function emr_dict_seed_defaults()
{
    dict_db_ensure();
}

/** 全部字典: [key => items[]] (前端 window.EmrDicts 口径) */
function emr_dicts()
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $out = [];
    try {
        dict_db_ensure();
        foreach (dict_db()->query("SELECT dkey, items FROM dicts ORDER BY builtin DESC, dkey") as $row) {
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
    dict_db_ensure();
    $rows = dict_db()->query("SELECT * FROM dicts ORDER BY builtin DESC, dkey")->fetchAll(PDO::FETCH_ASSOC);
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
    dict_db_ensure();
    $pdo = dict_db();
    $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
    $exists = $pdo->prepare("SELECT dkey FROM dicts WHERE dkey = ?");
    $exists->execute([$key]);
    if ($exists->fetchColumn()) {
        $upd = $pdo->prepare("UPDATE dicts SET name = ?, items = ?, updated_at = ?, updated_by = ? WHERE dkey = ?");
        $upd->execute([$name, $json, DateHelper::now(), $userId, $key]);
    } else {
        $ins = $pdo->prepare("INSERT INTO dicts (dkey, name, items, builtin, updated_at, updated_by) VALUES (?,?,?,0,?,?)");
        $ins->execute([$key, $name, $json, DateHelper::now(), $userId]);
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
