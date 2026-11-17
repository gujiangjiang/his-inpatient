<?php
// 062_hospital_branding.php - 医院第二名称与品牌资源版本
return [
    'version' => '062',
    'description' => '增加医院第二名称和品牌资源版本配置',
    'up' => function ($m) {
        if (!$m->hasTable('system_config')) return;
        $now = gmdate('Y-m-d H:i:s');
        $rows = [
            ['hospital_name2', '', 'system', '医院第二名称（选填）'],
            ['hospital_brand_revision', '1', 'internal', '医院品牌资源版本'],
        ];
        foreach ($rows as $row) {
            if (DB::selectOne("SELECT config_key FROM system_config WHERE config_key = ?", [$row[0]])) continue;
            DB::execute(
                "INSERT INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
                [$row[0], $row[1], $row[2], $row[3], $now]
            );
        }
    }
];
