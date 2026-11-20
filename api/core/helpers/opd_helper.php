<?php
// api/core/helpers/opd_helper.php - 门诊 / PACS 互通客户端 (一期: 只读查询 + 字典推送)
// 门诊开放接口: FHIR R4 (/api/fhir/r4) + 业务只读 (/api/external/his/read) + 字典同步 (/api/external/his/sync-catalog)

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

/** 通用 HTTP 请求 (cURL 优先, 回退流式); 返回 [httpCode, body]
 *  所有对外的出向互调（门诊 FHIR/业务、PACS）统一在此记录接口日志 */
function opd_http($method, $url, $headers = [], $body = null, $timeout = 8) {
    $__start = microtime(true);
    $__url = (string)$url;
    $__resp = '';
    $__code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($__url);
        /* 出站 TLS 证书校验默认开启 (与流式回退分支的默认行为对齐);
         * 内网自签环境可在 config.db 配 opd.tls_verify=0 关闭 (恢复旧不校验行为) */
        $__tlsVerify = (string)ConfigDB::get('opd.tls_verify', '1') !== '0';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $__tlsVerify,
            CURLOPT_SSL_VERIFYHOST => $__tlsVerify ? 2 : 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $__resp = curl_exec($ch);
        $__code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $__error = curl_error($ch);
        if ($__resp === false) $__resp = '请求失败: ' . $__error . net_diag_hint($__url);
    } else {
        $opts = ['http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ]];
        if ($body !== null) $opts['http']['content'] = $body;
        $fp = @fopen($__url, 'r', false, stream_context_create($opts));
        if ($fp === false) {
            $__resp = '请求失败' . net_diag_hint($__url);
        } else {
            $meta = stream_get_meta_data($fp);
            $__resp = stream_get_contents($fp);
            fclose($fp);
            foreach (($meta['wrapper_data'] ?? []) as $line) {
                if (preg_match('#^HTTP/\S+\s(\d{3})#', $line, $m)) {
                    $__code = (int)$m[1];
                    break;
                }
            }
        }
    }
    $__elapsed = (int)round((microtime(true) - $__start) * 1000);
    // 接口日志（静默降级，绝不影响业务）
    if (function_exists('log_interface')) {
        $__ok = $__code >= 200 && $__code < 400 && $__code !== 0;
        $__summary = $method . ' ' . $__code . '（' . $__elapsed . 'ms）';
        $__detail = $__ok ? '' : (is_string($__resp) ? mb_substr($__resp, 0, 500) : '');
        $__cat = strpos($__url, '/fhir/') !== false ? 'fhir' : (strpos($__url, 'pacs') !== false || strpos($__url, '/viewer') !== false ? 'pacs' : 'opd');
        $__path = parse_url($__url, PHP_URL_PATH) ?: $__url;
        $__endpoint = $__path;
        if (strpos($__url, '/external/his/read') !== false) {
            parse_str((string)parse_url($__url, PHP_URL_QUERY), $__q);
            $__endpoint .= '?action=' . ($__q['action'] ?? '');
        }
        try {
            log_interface($__cat, 'outbound', $__endpoint, $__ok, $__summary, '', $__detail, $__url);
        } catch (\Throwable $e) {
            // 接口日志失败静默
        }
    }
    return [$__code, $__resp];
}

/** FHIR 访问失败 → 可执行的中文提示 (定位: 网络/Token/Scope/版本) */
function opd_fhir_error_hint($http, $msg) {
    if ($http === 0) return $msg !== '' ? '无法连接门诊服务（' . $msg . '）' : '无法连接门诊服务（检查服务地址与网络）';
    if ($http === 401) return '鉴权失败：Token 无效或缺失（检查接口管理中 FHIR Token）';
    if ($http === 403) return '权限不足：门诊系统未授权该资源（检查该 Token 的 Scope / IP 白名单配置）';
    if ($http === 404) return '门诊系统不支持该资源（版本过旧？请升级门诊系统）';
    if ($http >= 500) return '门诊系统内部错误（请查看门诊系统日志）';
    return $msg !== '' ? $msg : ('HTTP ' . $http);
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
        $msg = '';
        if (is_array($json) && isset($json['issue'][0]['diagnostics'])) $msg = (string)$json['issue'][0]['diagnostics'];
        if ($code === 0 && $msg === '') $msg = trim((string)$body);   // 连接层失败: 透出 cURL 错误与网络栈诊断
        return ['ok' => false, 'http' => $code, 'total' => 0, 'entries' => [], 'error' => opd_fhir_error_hint($code, $msg)];
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

/** PACS 阅片地址模板 (归一化: 去首尾空白与多余斜杠, 保留协议头的 //) */
function opd_viewer_template() {
    $s = opd_settings();
    return preg_replace('#(?<!:)/{2,}#', '/', trim((string)$s['pacs_viewer_url']));
}

/** 拼 PACS 阅片直链 (模板占位符 {study_uid}) */
function opd_viewer_url($studyUid) {
    $s = opd_settings();
    if ($s['pacs_enabled'] !== '1' || $studyUid === '') return '';
    return str_replace('{study_uid}', rawurlencode($studyUid), opd_viewer_template());
}

/** 业务接口失败消息: 连接层失败(0)时透出 cURL 错误与网络栈诊断, 其余回退 HTTP 状态 */
function opd_transport_msg($json, $code, $body) {
    if (is_array($json) && isset($json['msg']) && $json['msg'] !== '') return (string)$json['msg'];
    if ($code === 0 && trim((string)$body) !== '') return trim((string)$body);
    return 'HTTP ' . $code;
}

/** 门诊业务只读接口 (action=ping/patient_get/visit_list/order_list...; $timeout 秒) */
function opd_his_read($action, $params = [], $timeout = 8) {
    $settings = opd_settings();
    $query = http_build_query(array_merge(['action' => $action], $params));
    $headers = [];
    if ($settings['opd_his_token'] !== '') $headers[] = 'X-HIS-Token: ' . $settings['opd_his_token'];
    list($code, $body) = opd_http('GET', opd_base_url() . '/api/external/his/read?' . $query, $headers, null, $timeout);
    $json = json_decode((string)$body, true);
    $ok = $code === 200 && is_array($json) && !empty($json['ok']);
    return ['ok' => $ok, 'http' => $code, 'data' => $json['data'] ?? null, 'msg' => opd_transport_msg($json, $code, $body)];
}

/** 门诊字典同步 (sync-catalog): 药品/检验/检查/处置项目 */
function opd_his_push_catalog($payload) {
    $settings = opd_settings();
    $headers = ['Content-Type: application/json; charset=utf-8'];
    if ($settings['opd_his_token'] !== '') $headers[] = 'X-HIS-Token: ' . $settings['opd_his_token'];
    list($code, $body) = opd_http('POST', opd_base_url() . '/api/external/his/sync-catalog', $headers, json_encode($payload, JSON_UNESCAPED_UNICODE), 20);
    $json = json_decode((string)$body, true);
    $ok = $code === 200 && is_array($json) && !empty($json['ok']);
    return ['ok' => $ok, 'http' => $code, 'data' => $json['data'] ?? null, 'msg' => opd_transport_msg($json, $code, $body)];
}

/** 住院检验/检查单推送 (sync-orders): 门诊登记出报告（住院开单即计费，一律视为已缴费） */
function opd_his_push_orders($payload) {
    $settings = opd_settings();
    $headers = ['Content-Type: application/json; charset=utf-8'];
    if ($settings['opd_his_token'] !== '') $headers[] = 'X-HIS-Token: ' . $settings['opd_his_token'];
    list($code, $body) = opd_http('POST', opd_base_url() . '/api/external/his/sync-orders', $headers, json_encode($payload, JSON_UNESCAPED_UNICODE), 15);
    $json = json_decode((string)$body, true);
    $ok = $code === 200 && is_array($json) && !empty($json['ok']);
    return ['ok' => $ok, 'http' => $code, 'data' => $json['data'] ?? null, 'msg' => opd_transport_msg($json, $code, $body)];
}

/** 对方系统（门诊）时间 → 本系统站点时区显示（Y-m-d H:i）。
 *  三系统契约：外部接口时间一律标准 UTC（ISO 8601 带 Z/偏移）；
 *  strtotime 精确解析时间点后按本系统站点时区渲染展示。 */
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
