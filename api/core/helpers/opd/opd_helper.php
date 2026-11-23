<?php
// api/core/helpers/opd/opd_helper.php - 门诊 / PACS 互通 (聚合入口)
// 门诊开放接口: FHIR R4 (/api/fhir/r4) + 业务只读 (/api/external/his/read) + 字典同步 (/api/external/his/sync-catalog)
// 实现按职责拆分至 opd/ 子目录 (本文件仅做加载编排, 对外函数名与加载顺序保持不变):
//   opd/opd_settings.php  集成配置读写 (integration_settings)
//   opd/opd_http.php      通用 HTTP 客户端 + 接口日志
//   opd/opd_fhir.php      FHIR R4 检索/患者匹配/资源解析
//   opd/opd_his.php       业务只读 + 字典/医嘱推送
//   opd/opd_pacs.php      PACS 阅片直链
//   opd/opd_common.php    时间格式化/标志映射/患者行解析

require_once __DIR__ . '/client/opd_settings.php';
require_once __DIR__ . '/client/opd_http.php';
require_once __DIR__ . '/client/opd_fhir.php';
require_once __DIR__ . '/client/opd_his.php';
require_once __DIR__ . '/client/opd_pacs.php';
require_once __DIR__ . '/client/opd_common.php';
