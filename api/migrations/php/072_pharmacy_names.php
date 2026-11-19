<?php
// api/migrations/php/072_pharmacy_names.php - 药房名称配置（处方打印显示）
// pharmacy_center_name / pharmacy_outpatient_name：处方按开具药房显示名称，
// 可在系统设置修改（如 门诊药房 → 门诊西药房），打印自动跟随。
return [
    'version' => '072',
    'description' => '药房名称配置: 中心/门诊药房可改名',
    'up' => function($m) {
        if (!$m->hasTable('system_config')) return;
        $rows = DB::select("SELECT config_key FROM system_config WHERE config_key IN ('pharmacy_center_name','pharmacy_outpatient_name')");
        $have = [];
        foreach ($rows as $r) $have[$r['config_key']] = true;
        if (!isset($have['pharmacy_center_name'])) {
            DB::execute("INSERT INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
                ['pharmacy_center_name', '中心药房', 'system', '中心药房名称（处方打印显示）', DateHelper::now()]);
        }
        if (!isset($have['pharmacy_outpatient_name'])) {
            DB::execute("INSERT INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
                ['pharmacy_outpatient_name', '门诊药房', 'system', '门诊药房名称（处方打印显示）', DateHelper::now()]);
        }
    }
];
