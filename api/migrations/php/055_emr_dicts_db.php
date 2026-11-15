<?php
// 055_emr_dicts_db.php - 全院统一字典入库 (emr_dicts)
// 决策: 字典存主业务库 (hospital) — 属全院业务数据: 随业务库备份/迁移/导出,
//       模板与文书以 dict 键引用; config 库仅存系统级配置 (时区/集成), 不放业务数据。
// 首次使用自动建表并写入内置字典 (懒创建见 api/core/emr_dicts.php, 本迁移覆盖常规升级路径)
return [
    'version' => '055',
    'description' => '全院统一字典入库 (民族/婚姻/职业/病史陈述者/有无等)',
    'up' => function ($m) {
        if (!$m->hasTable('emr_dicts')) {
            $m->createTable('emr_dicts', [
                'dkey' => ['type' => 'VARCHAR', 'length' => 50, 'primary' => true],
                'name' => ['type' => 'VARCHAR', 'length' => 50, 'notnull' => true],
                'items' => ['type' => 'TEXT', 'notnull' => true],
                'builtin' => ['type' => 'INTEGER', 'default' => 0], /* 内置字典 (不可删除, 可改内容) */
                'updated_at' => ['type' => 'TEXT'],
                'updated_by' => ['type' => 'INTEGER'],
            ], ['if_not_exists' => true]);
        }
        require_once __DIR__ . '/../../core/emr_dicts.php';
        emr_dict_seed_defaults();
    }
];
