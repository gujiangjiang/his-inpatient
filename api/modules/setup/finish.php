<?php
// api/modules/setup/finish.php - 向导完成：置 setup_completed=1（此后向导全部关闭）
require_once __DIR__ . '/_common.php';
setup_require_open();
setup_require_stage(array('done'));

DB::execute("INSERT OR REPLACE INTO system_config (config_key, config_value, config_group, description, updated_at) VALUES (?,?,?,?,?)",
    array('setup_completed', '1', 'system', '系统是否已初始化', DateHelper::now()));
ConfigDB::remove('setup.stage');   // 完成标记由 system_config 持有

Response::success(array('setup_completed' => true), '系统初始化完成');
