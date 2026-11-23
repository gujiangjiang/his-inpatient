<?php
// api/core/helpers/opd/client/opd_fhir.php - 门诊 FHIR R4 客户端 (检索/患者匹配/资源解析)

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
