<?php
// api/core/helpers/opd_helper.php - 门诊一体化 / PACS 互通客户端 (一期: 只读查询 + 字典推送)
// 门诊一体化开放接口: FHIR R4 (/api/fhir/r4) + 业务只读 (/api/external/his/read) + 字典同步 (/api/external/his/sync-catalog)

/** 集成配置默认值 */
function opd_defaults() {
    return [
        'opd_enabled' => '1',
        'opd_base_url' => 'http://127.0.0.1:8000',
        'opd_fhir_token' => '',
        'opd_his_token' => '',
        'pacs_enabled' => '1',
        'pacs_viewer_url' => 'http://127.0.0.1:8090/?r=viewer&uid={study_uid}',
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

/** 门诊一体化服务根地址 (不带末尾斜杠) */
function opd_base_url() {
    $s = opd_settings();
    return rtrim($s['opd_base_url'], '/');
}

/** 通用 HTTP 请求 (cURL 优先, 回退流式); 返回 [httpCode, body] */
function opd_http($method, $url, $headers = [], $body = null, $timeout = 8) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($resp === false) return [0, '请求失败: ' . $error];
        return [$code, $resp];
    }
    $opts = ['http' => [
        'method' => $method,
        'timeout' => $timeout,
        'ignore_errors' => true,
        'header' => implode("\r\n", $headers),
    ]];
    if ($body !== null) $opts['http']['content'] = $body;
    $fp = @fopen($url, 'r', false, stream_context_create($opts));
    if ($fp === false) return [0, '请求失败'];
    $meta = stream_get_meta_data($fp);
    $resp = stream_get_contents($fp);
    fclose($fp);
    $code = 0;
    foreach (($meta['wrapper_data'] ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s(\d{3})#', $line, $m)) {
            $code = (int)$m[1];
            break;
        }
    }
    return [$code, (string)$resp];
}

/** FHIR 检索: 返回 {ok, http, total, entries, error} */
function opd_fhir_request($resource, $params = []) {
    $settings = opd_settings();
    $url = opd_base_url() . '/api/fhir/r4/' . $resource . '?' . http_build_query($params);
    $headers = ['Accept: application/fhir+json'];
    if ($settings['opd_fhir_token'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $settings['opd_fhir_token'];
        $headers[] = 'X-API-Key: ' . $settings['opd_fhir_token'];
    }
    list($code, $body) = opd_http('GET', $url, $headers);
    $json = json_decode((string)$body, true);
    if ($code !== 200 || !is_array($json)) {
        $msg = 'HTTP ' . $code;
        if (is_array($json) && isset($json['issue'][0]['diagnostics'])) $msg = $json['issue'][0]['diagnostics'];
        return ['ok' => false, 'http' => $code, 'total' => 0, 'entries' => [], 'error' => $msg];
    }
    if (($json['resourceType'] ?? '') === 'Bundle') {
        $entries = [];
        foreach (($json['entry'] ?? []) as $e) {
            if (isset($e['resource'])) $entries[] = $e['resource'];
        }
        return ['ok' => true, 'http' => $code, 'total' => (int)($json['total'] ?? count($entries)), 'entries' => $entries, 'error' => ''];
    }
    return ['ok' => true, 'http' => $code, 'total' => 1, 'entries' => [$json], 'error' => ''];
}

/** 按身份证匹配门诊患者: {ok, patient(Patient 资源|null), error} */
function opd_fhir_find_patient($idCard) {
    if ($idCard === '' || $idCard === null) {
        return ['ok' => true, 'patient' => null, 'error' => '患者未登记身份证号'];
    }
    $r = opd_fhir_request('Patient', ['identifier' => $idCard, '_count' => 5]);
    return ['ok' => $r['ok'], 'patient' => $r['entries'][0] ?? null, 'error' => $r['error']];
}

/** 从资源 identifier 中按 system 片段取值 */
function opd_identifier_value($resource, $systemNeedle) {
    foreach (($resource['identifier'] ?? []) as $item) {
        if (isset($item['system']) && strpos($item['system'], $systemNeedle) !== false) {
            return $item['value'] ?? '';
        }
    }
    return '';
}

/** ImagingStudy → 纯 StudyInstanceUID (去掉 urn:oid: 前缀) */
function opd_study_uid($imagingStudy) {
    $v = opd_identifier_value($imagingStudy, 'urn:dicom:uid');
    return preg_replace('#^urn:oid:#', '', $v);
}

/** 资源 extension 取值 */
function opd_extension($resource, $url) {
    foreach (($resource['extension'] ?? []) as $ext) {
        if (($ext['url'] ?? '') === $url) {
            return $ext['valueString'] ?? ($ext['valueUri'] ?? '');
        }
    }
    return '';
}

/** 拼 PACS 阅片直链 (模板占位符 {study_uid}) */
function opd_viewer_url($studyUid) {
    $s = opd_settings();
    if ($s['pacs_enabled'] !== '1' || $studyUid === '') return '';
    return str_replace('{study_uid}', rawurlencode($studyUid), $s['pacs_viewer_url']);
}

/** 门诊业务只读接口 (action=ping/patient_get/visit_list/order_list...) */
function opd_his_read($action, $params = []) {
    $settings = opd_settings();
    $query = http_build_query(array_merge(['action' => $action], $params));
    $headers = [];
    if ($settings['opd_his_token'] !== '') $headers[] = 'X-HIS-Token: ' . $settings['opd_his_token'];
    list($code, $body) = opd_http('GET', opd_base_url() . '/api/external/his/read?' . $query, $headers);
    $json = json_decode((string)$body, true);
    $ok = $code === 200 && is_array($json) && !empty($json['ok']);
    return ['ok' => $ok, 'http' => $code, 'data' => $json['data'] ?? null, 'msg' => $json['msg'] ?? ('HTTP ' . $code)];
}

/** 门诊字典同步 (sync-catalog): 药品/检验/检查/处置项目 */
function opd_his_push_catalog($payload) {
    $settings = opd_settings();
    $headers = ['Content-Type: application/json; charset=utf-8'];
    if ($settings['opd_his_token'] !== '') $headers[] = 'X-HIS-Token: ' . $settings['opd_his_token'];
    list($code, $body) = opd_http('POST', opd_base_url() . '/api/external/his/sync-catalog', $headers, json_encode($payload, JSON_UNESCAPED_UNICODE), 20);
    $json = json_decode((string)$body, true);
    $ok = $code === 200 && is_array($json) && !empty($json['ok']);
    return ['ok' => $ok, 'http' => $code, 'data' => $json['data'] ?? null, 'msg' => $json['msg'] ?? ('HTTP ' . $code)];
}

/** ISO 时间 → Y-m-d H:i */
function opd_fmt_datetime($iso) {
    if (!$iso) return '';
    $ts = strtotime((string)$iso);
    return $ts ? date('Y-m-d H:i', $ts) : (string)$iso;
}

/** Observation interpretation → HIS 标志 (critical/H/L/'') */
function opd_observation_flag($res) {
    $code = strtoupper((string)($res['interpretation'][0]['coding'][0]['code'] ?? ''));
    if (in_array($code, ['HH', 'LL', 'CRIT', 'PANIC', 'AA', 'CRITICAL'], true)) return 'critical';
    if ($code === 'H' || $code === 'HIGH' || $code === '高') return 'H';
    if ($code === 'L' || $code === 'LOW' || $code === '低') return 'L';
    return '';
}

/** 解析当前用户可见的患者行 (含科室/病区名, 复用 can_access_patient 数据权限) */
function opd_resolve_patient($patientId) {
    $patient = DB::selectOne(
        "SELECT p.*, d.name AS department_name, w.name AS ward_name FROM patients p
         LEFT JOIN departments d ON d.id = p.department_id
         LEFT JOIN wards w ON w.id = p.ward_id WHERE p.id = ?",
        [$patientId]
    );
    if (!$patient || !can_access_patient(Auth::user(), $patientId)) return null;
    return $patient;
}
