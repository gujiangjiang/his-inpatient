<?php
// api/core/bootstrap.php - 应用基础设施统一加载
// 将原先散落在入口(index.php/脚本)的基础设施 require 收敛为分组清单;
// 顺序与原 index.php 逐项一致(配置 → 核心类 → helpers → 字典 → 迁移守卫 → 路由表)。
// 新增 helper 只需在对应分组追加一行。

$CORE = __DIR__;

/* ---------- 配置 ---------- */
require_once dirname($CORE) . '/config.php';

/* ---------- 核心类 ---------- */
require_once $CORE . '/db.php';
require_once $CORE . '/Cache.php';
require_once $CORE . '/auth.php';
require_once $CORE . '/response.php';
require_once $CORE . '/validator.php';
require_once $CORE . '/setup_guard.php';

/* ---------- 时间/基础数据 helpers ---------- */
require_once $CORE . '/helpers/time_helper.php';
require_once $CORE . '/helpers/date_helper.php';
require_once $CORE . '/helpers/age_helper.php';
require_once $CORE . '/helpers/print_snapshot_helper.php';
require_once $CORE . '/helpers/print_snapshot_builders.php';
require_once $CORE . '/helpers/string_helper.php';
require_once $CORE . '/helpers/id_generator_helper.php';

/* ---------- 权限/数据范围 helpers ---------- */
require_once $CORE . '/helpers/department_helper.php';
require_once $CORE . '/helpers/access_scope_helper.php';
require_once $CORE . '/helpers/fee_helper.php';
require_once $CORE . '/helpers/patient_helper.php';
require_once $CORE . '/helpers/dept_helper.php';

/* ---------- 病历 (EMR) helpers ---------- */
require_once $CORE . '/helpers/emr_guard_helper.php';
require_once $CORE . '/helpers/emr_slot_value.php';
require_once $CORE . '/helpers/emr_calc_helper.php';
require_once $CORE . '/helpers/emr_diagnosis_helper.php';
require_once $CORE . '/dict_db.php';
require_once $CORE . '/emr_dicts.php';

/* ---------- 医嘱/药房 helpers ---------- */
require_once $CORE . '/helpers/order_set_helper.php';
require_once $CORE . '/helpers/order_state_helper.php';
require_once $CORE . '/helpers/dual_pharmacy_helper.php';
require_once $CORE . '/helpers/opd_pharmacy_helper.php';

/* ---------- 通知/审计/日志 helpers ---------- */
require_once $CORE . '/helpers/notify_helper.php';
require_once $CORE . '/helpers/audit_helper.php';
require_once $CORE . '/helpers/cache_driver_helper.php';
require_once $CORE . '/helpers/log_helper.php';

/* ---------- 门诊互通 helpers ---------- */
require_once $CORE . '/helpers/pub_helper.php';
require_once $CORE . '/helpers/net_diag_helper.php';
require_once $CORE . '/helpers/opd_helper.php';
require_once $CORE . '/helpers/opd_order_push.php';
require_once $CORE . '/helpers/catalog_sync.php';

/* ---------- 迁移守卫与路由表 ---------- */
require_once $CORE . '/migration.php';
require_once $CORE . '/schema_guard.php';
require_once dirname($CORE) . '/routes.php';
