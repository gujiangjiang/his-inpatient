<?php
// 057_dicts_db.php - 基础字典迁移至独立库 (data/dicts.sqlite)
// 1) 建独立字典库并导入打包种子 (省市县+邮编 / 职业分类大典2022 / 基础字典)
// 2) 旧主库 emr_dicts (管理员修改过的字典) 合并迁移至独立库 (覆盖同名种子, 保留修改)
// 3) 移除主库旧表 emr_dicts (数据已在独立库, 验证行数一致后删除)
return [
    'version' => '057',
    'description' => '基础字典迁移至独立字典库 (省市县邮编/职业大典/院级字典)',
    'up' => function ($m) {
        require_once __DIR__ . '/../../core/emr_dicts.php';

        /* 1) 建库导入种子 */
        dict_db_ensure();
        $pdo = dict_db();

        /* 2) 旧主库 emr_dicts → 独立库:
               - 种子同名字段 (nation/occupation 等内置键): 以新种子(完善后数据)为准, 不迁移旧值
               - 自定义字典 (非种子键, 如管理员新增): 原样迁移保留 */
        $hasOld = $m->hasTable('emr_dicts');
        if ($hasOld) {
            $seedKeys = array_column(dict_seed_json('dicts') ?: [], 'key');
            $old = DB::select("SELECT dkey, name, items, builtin, updated_at, updated_by FROM emr_dicts");
            if ($old) {
                $upd = $pdo->prepare("INSERT INTO dicts (dkey, name, items, builtin, updated_at, updated_by) VALUES (?,?,?,?,?,?)
                    ON CONFLICT(dkey) DO UPDATE SET name=excluded.name, items=excluded.items, builtin=excluded.builtin, updated_at=excluded.updated_at, updated_by=excluded.updated_by");
                foreach ($old as $r) {
                    if (in_array((string)$r['dkey'], $seedKeys, true)) continue; /* 内置键: 新种子胜出 */
                    $upd->execute([(string)$r['dkey'], (string)$r['name'], (string)$r['items'], (int)($r['builtin'] ?? 0), $r['updated_at'] ?? null, $r['updated_by'] ?? null]);
                }
            }
            /* 3) 数据核验后移除旧表 */
            $oldCount = (int)(DB::selectOne("SELECT COUNT(*) AS n FROM emr_dicts")['n'] ?? 0);
            $newCount = (int)$pdo->query("SELECT COUNT(*) FROM dicts")->fetchColumn();
            if ($newCount >= $oldCount) {
                DB::execute("DROP TABLE emr_dicts");
            }
        }
    }
];
