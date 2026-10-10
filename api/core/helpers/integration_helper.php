<?php
// api/core/helpers/integration_helper.php - 外部系统对接 (门诊一体化预留)
// 说明: 当前为预留接口, 后续在此实现 FHIR / HL7 / HIS / DICOM 适配器

/** 按业务类型获取启用的对接端点 */
function integration_endpoints_for($bizType) {
    return DB::select(
        "SELECT * FROM integration_endpoints WHERE biz_type = ? AND enabled = 1 ORDER BY id ASC",
        [$bizType]
    );
}

/**
 * 对外派发业务数据 (预留)
 * @param string $bizType  lab 检验 | exam 检查 | prescription 处方 | treatment 处置 | patient 患者
 * @param array  $payload  业务数据
 * @return array  {status: pending|skipped, message, endpoint}
 */
function integration_dispatch($bizType, array $payload) {
    $endpoints = integration_endpoints_for($bizType);
    if (!$endpoints) {
        return ['status' => 'skipped', 'message' => '未配置对接端点'];
    }
    $endpoint = $endpoints[0];
    // TODO: 按 $endpoint['system_type'] + auth_type 实现各标准适配器 (FHIR Bundle / HL7 v2 消息 / DICOM 等)
    return [
        'status' => 'pending',
        'message' => '接口已预留, 适配器待实现',
        'endpoint' => $endpoint['name'],
        'system_type' => $endpoint['system_type'],
    ];
}
