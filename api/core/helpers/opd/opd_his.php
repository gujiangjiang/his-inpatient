<?php
// api/core/helpers/opd/opd_his.php - 门诊业务只读接口与推送 (sync-catalog / sync-orders)

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
