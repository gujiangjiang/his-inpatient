<?php
// api/routes.php - 路由表定义 (供 api/index.php 和 public/worker.php 共享)

function getRoutes() {
    return [
        // 认证
        'auth/check' => ['auth/session/check.php', []],
        'auth/login' => ['auth/session/login.php', []],
        'auth/captcha' => ['auth/session/captcha.php', []],
        'auth/logout' => ['auth/session/logout.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'auth/setup-status' => ['auth/setup/status.php', []],
        'auth/setup' => ['auth/setup/setup.php', []],

        // 初始化向导（多步：环境检测 → 数据库 → 迁移 → 站点信息 → 管理员 → 完成）
        'setup/status' => ['setup/status.php', []],
        'setup/database' => ['setup/database.php', []],
        'setup/migrate' => ['setup/migrate.php', []],
        'setup/cache' => ['setup/cache.php', []],
        'setup/site' => ['setup/site.php', []],
        'setup/admin' => ['setup/admin.php', []],
        'setup/finish' => ['setup/finish.php', []],

        // 后台管理
        'admin/department-list' => ['admin/department/list.php', ['admin', 'doctor', 'nurse']],
        'admin/department-save' => ['admin/department/save.php', ['admin']],
        'admin/ward-list' => ['admin/ward/list.php', ['admin', 'doctor', 'nurse']],
        'admin/ward-save' => ['admin/ward/save.php', ['admin']],
        'admin/user-list' => ['admin/user/list.php', ['admin']],
        'admin/user-save' => ['admin/user/save.php', ['admin']],
        'admin/sys-config-list' => ['admin/config/sys_config_list.php', ['admin']],
        'admin/sys-config-save' => ['admin/config/sys_config_save.php', ['admin']],
        'admin/branding-save' => ['admin/config/branding_save.php', ['admin']],
        'admin/timezone-get' => ['admin/config/timezone_get.php', ['admin']],
        'admin/timezone-save' => ['admin/config/timezone_save.php', ['admin']],
        'admin/integration-list' => ['admin/integration/list.php', ['admin']],
        'admin/integration-save' => ['admin/integration/save.php', ['admin']],
        'admin/integration-delete' => ['admin/integration/delete.php', ['admin']],
        'admin/integration-config' => ['admin/integration/config.php', ['admin']],
        'admin/integration-config-save' => ['admin/integration/config_save.php', ['admin']],
        'admin/integration-test' => ['admin/integration/test.php', ['admin']],
        'dict/region-tree' => ['dict/region_tree.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'notify/list' => ['notify/list.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'notify/read' => ['notify/read.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'notify/stream' => ['notify/stream.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'admin/query' => ['admin/query/query.php', ['admin']],
        'admin/drugs' => ['admin/catalog/drugs.php', ['admin']],
        'admin/logs' => ['admin/system/logs.php', ['admin']],
        'admin/review-history' => ['admin/audit/history.php', ['admin']],
        'admin/audit' => ['admin/audit/center.php', ['admin']],
        'admin/db-center' => ['admin/system/db_center.php', ['admin']],
        'admin/cache-center' => ['admin/system/cache_center.php', ['admin']],
        'admin/dict-list' => ['admin/dict/list.php', ['admin']],
        'admin/dict-save' => ['admin/dict/save.php', ['admin']],
        'admin/integration-push-catalog' => ['admin/integration/push_catalog.php', ['admin']],
        'admin/integration-retry-orders' => ['admin/integration/retry_orders.php', ['admin']],
        'admin/integration-audit-list' => ['admin/integration/audit_list.php', ['admin']],
        'admin/integration-audit-clear' => ['admin/integration/audit_clear.php', ['admin']],

        // 患者管理
        'patients/list' => ['patients/patient/list.php', ['admin', 'doctor', 'nurse']],
        'patients/get' => ['patients/patient/get.php', ['admin', 'doctor', 'nurse']],
        'patients/save' => ['patients/patient/save.php', ['admin', 'doctor']],
        'patients/discharge' => ['patients/patient/discharge.php', ['admin', 'doctor']],
        'patients/beds' => ['patients/bed/list.php', ['admin', 'doctor', 'nurse']],

        // 病历
        'emr/save' => ['emr/station/save.php', ['admin', 'doctor']],
        'emr/list' => ['emr/station/list.php', ['admin', 'doctor', 'nurse']],
        'emr/get' => ['emr/station/get.php', ['admin', 'doctor', 'nurse']],
        'emr/icd-search' => ['emr/diagnosis/icd_search.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'emr/template-list' => ['emr/template/list.php', ['admin', 'doctor']],
        'emr/template-get' => ['emr/template/get.php', ['admin', 'doctor']],
        'emr/template-save' => ['emr/template/save.php', ['admin', 'doctor']],
        'emr/template-use' => ['emr/template/use.php', ['admin', 'doctor']],
        'emr/template-delete' => ['emr/template/delete.php', ['admin', 'doctor']],
        'emr/template-publish' => ['emr/template/publish.php', ['admin', 'doctor']],
        'emr/template-review' => ['emr/template/review.php', ['admin']],
        'emr/content-list' => ['emr/content/list.php', ['admin', 'doctor', 'nurse']],
        'emr/content-save' => ['emr/content/save.php', ['admin', 'doctor']],
        'emr/content-delete' => ['emr/content/delete.php', ['admin', 'doctor']],
        'emr/content-publish' => ['emr/content/publish.php', ['admin', 'doctor']],
        'emr/content-review' => ['emr/content/review.php', ['admin']],

        // 药房
        'pharmacy/medication-list' => ['pharmacy/medication/list.php', ['admin', 'pharmacist', 'doctor']],
        'pharmacy/drug-search' => ['pharmacy/medication/search.php', ['admin', 'doctor', 'pharmacist']],
        'pharmacy/medication-save' => ['pharmacy/medication/save.php', ['admin', 'pharmacist']],
        'pharmacy/pending-queue' => ['pharmacy/queue/pending.php', ['admin', 'pharmacist']],
        'pharmacy/dispensing-save' => ['pharmacy/dispensing/save.php', ['admin', 'pharmacist']],
        'pharmacy/dispensing-list' => ['pharmacy/dispensing/list.php', ['admin', 'pharmacist']],
        // 双药房：中心药房摆药/发药（queue/dispense/deliver）
        'pharmacy/dispense' => ['pharmacy/dispensing/dispense.php', ['admin', 'pharmacist']],

        // 处方 (药品项目库来自门诊)
        'rx/catalog' => ['rx/catalog.php', ['admin', 'doctor', 'pharmacist']],

        // 护士
        'nurse/patient-list' => ['nurse/patient/list.php', ['admin', 'nurse']],
        'nurse/order-list' => ['nurse/order/list.php', ['admin', 'nurse']],
        'nurse/nursing-action' => ['nurse/order/action.php', ['admin', 'nurse']],
        'nurse/vitals-save' => ['nurse/vitals/save.php', ['admin', 'nurse']],
        'nurse/vitals-list' => ['nurse/vitals/list.php', ['admin', 'nurse']],
        // 护士站预摆药（申请/取消/列表）
        'nurse/prepare' => ['nurse/order/prepare.php', ['admin', 'nurse']],
        // 护士站床位一览表 + 护理文书 (体温单/护理记录单/常规护理文书)
        'nurse/bedboard' => ['nurse/bed/bedboard.php', ['admin', 'nurse']],
        'nurse/temperature' => ['nurse/vitals/temperature.php', ['admin', 'nurse', 'doctor']],
        'nurse/nursing-notes' => ['nurse/docs/notes.php', ['admin', 'nurse', 'doctor']],
        'nurse/nursing-docs' => ['nurse/docs/documents.php', ['admin', 'nurse', 'doctor']],
        'nurse/transfer-bed' => ['nurse/bed/transfer_bed.php', ['admin', 'nurse']],
        'nurse/leave-ward' => ['nurse/admission/leave_ward.php', ['admin', 'nurse']],
        'nurse/admit' => ['nurse/admission/admit.php', ['admin', 'nurse']],
        'nurse/verify-batch' => ['nurse/order/verify_batch.php', ['admin', 'nurse']],
        'nurse/order-sheet' => ['nurse/print/order_sheet.php', ['admin', 'nurse']],
        'nurse/execution-label' => ['nurse/print/execution_label.php', ['admin', 'nurse']],
        'nurse/execution-sheet-list' => ['nurse/print/sheet_list.php', ['admin', 'nurse']],
        'nurse/execution-sheet-print' => ['nurse/print/sheet_print.php', ['admin', 'nurse']],
        'nurse/nursing-link' => ['nurse/docs/link.php', ['admin', 'nurse', 'doctor']],
        'nurse/temperature-chart' => ['nurse/vitals/chart.php', ['admin', 'nurse', 'doctor']],
        'nurse/charges' => ['nurse/charge/charges.php', ['admin', 'nurse']],
        'nurse/patient-info' => ['nurse/patient/info.php', ['admin', 'nurse']],

        // 医嘱
        'orders/save' => ['orders/order/save.php', ['admin', 'doctor']],
        'orders/list' => ['orders/order/list.php', ['admin', 'doctor', 'nurse']],
        'orders/update' => ['orders/order/update.php', ['admin', 'doctor']],
        'orders/group-add' => ['orders/order/group_add.php', ['admin', 'doctor']],
        'orders/quick-search' => ['orders/order/quick_search.php', ['admin', 'doctor']],
        'orders/verify' => ['orders/order/verify.php', ['admin', 'doctor', 'nurse']],
        'orders/status' => ['orders/order/status.php', ['admin', 'doctor', 'nurse']],
        'orders/submit' => ['orders/order/submit.php', ['admin', 'doctor']],
        'orders/copy' => ['orders/order/copy.php', ['admin', 'doctor']],
        'orders/invalidate' => ['orders/order/invalidate.php', ['admin', 'doctor']],
        'orders/stop' => ['orders/order/stop.php', ['admin', 'doctor']],
        'orders/rx-list' => ['orders/rx/list.php', ['admin', 'doctor']],
        'orders/rx-void' => ['orders/rx/void.php', ['admin', 'doctor']],
        'orders/batch-delete' => ['orders/order/batch_delete.php', ['admin', 'doctor']],
        'orders/set-list' => ['orders/set/list.php', ['admin', 'doctor']],
        'orders/set-save' => ['orders/set/save.php', ['admin', 'doctor']],
        'orders/set-apply' => ['orders/set/apply.php', ['admin', 'doctor']],
        'orders/set-delete' => ['orders/set/delete.php', ['admin', 'doctor']],

        // 病历 (结构化)
        'emr/category-list' => ['emr/station/category_list.php', ['admin', 'doctor']],
        'emr/category-save' => ['emr/station/category_save.php', ['admin']],
        'emr/document-list' => ['emr/document/list.php', ['admin', 'doctor', 'nurse']],
        'emr/document-batch-print' => ['emr/document/batch_print.php', ['admin', 'doctor']],
        'emr/document-get' => ['emr/document/get.php', ['admin', 'doctor', 'nurse']],
        'emr/document-save' => ['emr/document/save.php', ['admin', 'doctor']],
        'emr/document-sign' => ['emr/document/sign.php', ['admin', 'doctor']],
        'emr/document-delete' => ['emr/document/delete.php', ['admin', 'doctor']],
        'emr/maintenance-request' => ['emr/document/maintenance_request.php', ['admin', 'doctor']],
        'emr/maintenance-list' => ['emr/document/maintenance_list.php', ['admin', 'doctor']],
        'emr/maintenance-review' => ['emr/document/maintenance_review.php', ['admin']],
        'emr/field-values' => ['emr/document/field_values.php', ['admin', 'doctor', 'nurse']],
        'emr/dept-doctors' => ['emr/station/dept_doctors.php', ['admin', 'doctor', 'nurse']],
        'emr/discharge-check' => ['emr/station/discharge_check.php', ['admin', 'doctor']],
        'emr/qc-status' => ['emr/station/qc_status.php', ['admin', 'doctor', 'nurse']],
        'emr/discharge' => ['emr/station/discharge.php', ['admin', 'doctor']],
        'emr/version-list' => ['emr/document/version_list.php', ['admin', 'doctor']],
        'emr/version-restore' => ['emr/document/version_restore.php', ['admin', 'doctor']],

        // 诊断 (患者诊断明细 + 常用诊断模板)
        'emr/diagnosis-list' => ['emr/diagnosis/list.php', ['admin', 'doctor', 'nurse']],
        'emr/diagnosis-save' => ['emr/diagnosis/save.php', ['admin', 'doctor']],
        'emr/diagnosis-delete' => ['emr/diagnosis/delete.php', ['admin', 'doctor']],
        'emr/diagnosis-template-list' => ['emr/diagnosis/template_list.php', ['admin', 'doctor']],
        'emr/diagnosis-template-save' => ['emr/diagnosis/template_save.php', ['admin', 'doctor']],
        'emr/diagnosis-template-delete' => ['emr/diagnosis/template_delete.php', ['admin', 'doctor']],
        'emr/diagnosis-match-get' => ['emr/diagnosis/match_get.php', ['admin', 'doctor', 'nurse']],
        'emr/diagnosis-match-save' => ['emr/diagnosis/match_save.php', ['admin', 'doctor']],
        'emr/diagnosis-sync' => ['emr/diagnosis/sync.php', ['admin', 'doctor']],

        // 患者上下文
        'patients/incoming' => ['patients/incoming/queue.php', ['admin', 'doctor']],
        'patients/assign' => ['patients/patient/assign.php', ['admin', 'doctor']],
        'patients/assignment' => ['patients/incoming/assignment.php', ['admin', 'doctor']],
        'patients/my-patients' => ['patients/incoming/mine.php', ['admin', 'doctor']],
        'patients/by-department' => ['patients/incoming/by_department.php', ['admin', 'doctor']],
        'patients/funds' => ['patients/patient/funds.php', ['admin', 'doctor', 'nurse', 'pharmacist']],

        // 医生科室授权
        'doctors/departments' => ['doctors/departments.php', ['admin', 'doctor']],
        'doctors/list' => ['doctors/list.php', ['admin', 'doctor']],

        // 检验
        'lab/save' => ['lab/order/save.php', ['admin', 'doctor']],
        'lab/list' => ['lab/order/list.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'lab/detail' => ['lab/order/detail.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'lab/collect' => ['lab/order/collect.php', ['admin', 'nurse']],
        'lab/barcode-print' => ['lab/order/barcode_print.php', ['admin', 'nurse', 'lab_tech']],
        'lab/barcode-print-batch' => ['lab/order/barcode_print_batch.php', ['admin', 'nurse', 'lab_tech']],
        'lab/result' => ['lab/order/result.php', ['admin', 'lab_tech']],
        'lab/catalog' => ['lab/catalog/catalog.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'lab/panel-list' => ['lab/catalog/panel_list.php', ['admin', 'doctor']],
        'lab/panel-save' => ['lab/catalog/panel_save.php', ['admin', 'doctor']],
        'lab/panel-delete' => ['lab/catalog/panel_delete.php', ['admin', 'doctor']],
        'lab/apply' => ['lab/order/apply.php', ['admin', 'doctor']],
        'lab/delete' => ['lab/order/delete.php', ['admin', 'doctor']],

        // 检查
        'exam/save' => ['exam/report/save.php', ['admin', 'doctor']],
        'exam/list' => ['exam/report/list.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'exam/detail' => ['exam/report/detail.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'exam/report' => ['exam/report/report.php', ['admin', 'lab_tech']],
        'exam/catalog' => ['exam/catalog/catalog.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'exam/apply' => ['exam/report/apply.php', ['admin', 'doctor']],
        'exam/delete' => ['exam/report/delete.php', ['admin', 'doctor']],

        // 会诊
        'consult/save' => ['consult/save.php', ['admin', 'doctor']],
        'consult/list' => ['consult/list.php', ['admin', 'doctor']],
        'consult/reply' => ['consult/reply.php', ['admin', 'doctor']],
        'consult/delete' => ['consult/delete.php', ['admin', 'doctor']],

        // 病案首页
        'case/get' => ['case_front_page/record/get.php', ['admin', 'doctor']],
        'case/save' => ['case_front_page/record/save.php', ['admin', 'doctor']],
        'case/autofill' => ['case_front_page/record/autofill.php', ['admin', 'doctor']],
        'case/surgery-save' => ['case_front_page/surgery/save.php', ['admin', 'doctor']],
        'case/surgery-list' => ['case_front_page/surgery/list.php', ['admin', 'doctor']],
        'surgery/apply' => ['surgery/apply.php', ['admin', 'doctor']],
        'surgery/list' => ['surgery/list.php', ['admin', 'doctor', 'nurse']],
        'surgery/cancel' => ['surgery/cancel.php', ['admin', 'doctor']],

        // 门诊病历查询
        'outpatient/search' => ['outpatient/search.php', ['admin', 'doctor']],
        'outpatient/mock-data' => ['outpatient/dev/mock_data.php', []],

        // 门诊互通 (一期: 只读) - 患者记录/检验结果/检查报告 + PACS 影像
        'outpatient/records' => ['outpatient/interop/records.php', ['admin', 'doctor', 'nurse']],
        'outpatient/labs' => ['outpatient/interop/labs.php', ['admin', 'doctor', 'nurse']],
        'outpatient/exams' => ['outpatient/interop/exams.php', ['admin', 'doctor', 'nurse']],
    ];
}
