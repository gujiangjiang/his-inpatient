<?php
// api/core/bootstrap.php - 应用基础设施统一加载
// 将原先散落在入口(index.php/脚本)的基础设施 require 收敛为分组清单;
// 顺序与原 index.php 逐项一致(配置 → 核心类 → helpers → 字典 → 迁移守卫 → 路由表)。
// 新增 helper 只需在对应分组追加一行; core 按技术分层子目录:
// http/ auth/ db/ cache/ schema/ site/ dict/ emr/ print/ + helpers/{业务域}/

$CORE = __DIR__;

/* ---------- 配置 ---------- */
require_once dirname($CORE) . '/config.php';

/* ---------- 核心类 ---------- */
require_once $CORE . '/db/db.php';
require_once $CORE . '/cache/Cache.php';
require_once $CORE . '/auth/auth.php';
require_once $CORE . '/http/response.php';
require_once $CORE . '/http/validator.php';
require_once $CORE . '/schema/setup_guard.php';

/* ---------- 时间/基础数据 helpers ---------- */
require_once $CORE . '/helpers/time/time_helper.php';
require_once $CORE . '/helpers/time/date_helper.php';
require_once $CORE . '/helpers/patient/age_helper.php';
require_once $CORE . '/print/print_snapshot_helper.php';
require_once $CORE . '/print/print_snapshot_builders.php';
require_once $CORE . '/helpers/string/string_helper.php';
require_once $CORE . '/helpers/id/id_generator_helper.php';

/* ---------- 权限/数据范围 helpers ---------- */
require_once $CORE . '/helpers/org/department_helper.php';
require_once $CORE . '/helpers/org/access_scope_helper.php';
require_once $CORE . '/helpers/fee/fee_helper.php';
require_once $CORE . '/helpers/patient/patient_helper.php';
require_once $CORE . '/helpers/org/dept_helper.php';

/* ---------- 病历 (EMR) helpers ---------- */
require_once $CORE . '/helpers/emr/emr_guard_helper.php';
require_once $CORE . '/helpers/emr/emr_slot_value.php';
require_once $CORE . '/helpers/emr/emr_calc_helper.php';
require_once $CORE . '/helpers/emr/emr_diagnosis_helper.php';
require_once $CORE . '/db/dict_db.php';
require_once $CORE . '/dict/emr_dicts.php';

/* ---------- 医嘱/药房 helpers ---------- */
require_once $CORE . '/helpers/order/order_set_helper.php';
require_once $CORE . '/helpers/order/order_state_helper.php';
require_once $CORE . '/helpers/pharmacy/dual_pharmacy_helper.php';
require_once $CORE . '/helpers/pharmacy/opd_pharmacy_helper.php';

/* ---------- 通知/审计/日志 helpers ---------- */
require_once $CORE . '/helpers/notify/notify_helper.php';
require_once $CORE . '/helpers/audit/audit_helper.php';
require_once $CORE . '/helpers/cache/cache_driver_helper.php';
require_once $CORE . '/helpers/audit/log_helper.php';

/* ---------- 门诊互通 helpers ---------- */
require_once $CORE . '/helpers/emr/pub_helper.php';
require_once $CORE . '/helpers/opd/net_diag_helper.php';
require_once $CORE . '/helpers/opd/opd_helper.php';
require_once $CORE . '/helpers/opd/opd_order_push.php';
require_once $CORE . '/helpers/opd/catalog_sync.php';

/* ---------- 迁移守卫与路由表 ---------- */
require_once $CORE . '/schema/migration.php';
require_once $CORE . '/schema/schema_guard.php';
require_once dirname($CORE) . '/routes.php';
