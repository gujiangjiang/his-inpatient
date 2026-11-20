<?php
// api/core/helpers/opd/opd_settings.php - 门诊互通集成配置读写 (config 存 integration_settings 表)

/** 集成配置默认值 */
function opd_defaults() {
    return [
        'opd_enabled' => '1',
        'opd_base_url' => 'http://127.0.0.1:8000',
        'opd_fhir_token' => '',
        'opd_his_token' => '',
        'pacs_enabled' => '1',
        'pacs_viewer_url' => 'http://127.0.0.1:8090/?r=viewer&uid={study_uid}',
        'last_test_at' => '',
        'last_test_ok' => '',
    ];
}

/** 读取全部集成配置 */
function opd_settings() {
    $map = opd_defaults();
    if (!DB::table('integration_settings')) return $map;
    foreach (DB::select("SELECT ckey, cvalue FROM integration_settings") as $r) {
        $map[$r['ckey']] = $r['cvalue'];
    }
    return $map;
}

/** 保存多个配置键值 */
function opd_settings_save($kv) {
    foreach ($kv as $k => $v) {
        $exists = DB::selectOne("SELECT ckey FROM integration_settings WHERE ckey = ?", [$k]);
        if ($exists) {
            DB::execute("UPDATE integration_settings SET cvalue = ? WHERE ckey = ?", [$v, $k]);
        } else {
            DB::execute("INSERT INTO integration_settings (ckey, cvalue) VALUES (?, ?)", [$k, $v]);
        }
    }
}

/** 门诊服务根地址 (不带末尾斜杠) */
function opd_base_url() {
    $s = opd_settings();
    return rtrim($s['opd_base_url'], '/');
}
