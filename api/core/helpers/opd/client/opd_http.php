<?php
// api/core/helpers/opd/client/opd_http.php - 门诊/PACS 通用 HTTP 客户端 (cURL 优先, 回退流式)
// 所有对外的出向互调（门诊 FHIR/业务、PACS）统一在此记录接口日志

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
