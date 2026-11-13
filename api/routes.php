<?php
// api/routes.php - 路由表定义 (供 api/index.php 和 public/worker.php 共享)

function getRoutes() {
    return [
        // 认证
        'auth/check' => ['auth/check.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'auth/login' => ['auth/login.php', []],
        'auth/logout' => ['auth/logout.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'auth/setup-status' => ['auth/setup_status.php', []],
        'auth/setup' => ['auth/setup.php', []],

        // 初始化向导（多步：环境检测 → 数据库 → 迁移 → 站点信息 → 管理员 → 完成）
        'setup/status' => ['setup/status.php', []],
        'setup/database' => ['setup/database.php', []],
        'setup/migrate' => ['setup/migrate.php', []],
        'setup/site' => ['setup/site.php', []],
        'setup/admin' => ['setup/admin.php', []],
        'setup/finish' => ['setup/finish.php', []],

        // 后台管理
        'admin/department-list' => ['admin/department/department_list.php', ['admin', 'doctor', 'nurse']],
        'admin/department-save' => ['admin/department/department_save.php', ['admin']],
        'admin/ward-list' => ['admin/ward/ward_list.php', ['admin', 'doctor', 'nurse']],
        'admin/ward-save' => ['admin/ward/ward_save.php', ['admin']],
        'admin/user-list' => ['admin/user/user_list.php', ['admin']],
        'admin/user-save' => ['admin/user/user_save.php', ['admin']],
        'admin/api-config-list' => ['admin/config/api_config_list.php', ['admin']],
        'admin/api-config-save' => ['admin/config/api_config_save.php', ['admin']],
        'admin/sys-config-list' => ['admin/config/sys_config_list.php', ['admin']],
        'admin/sys-config-save' => ['admin/config/sys_config_save.php', ['admin']],
        'admin/timezone-get' => ['admin/config/timezone_get.php', ['admin']],
        'admin/timezone-save' => ['admin/config/timezone_save.php', ['admin']],
        'admin/integration-list' => ['admin/integration/integration_list.php', ['admin']],
        'admin/integration-save' => ['admin/integration/integration_save.php', ['admin']],
        'admin/integration-delete' => ['admin/integration/integration_delete.php', ['admin']],
        'admin/integration-config' => ['admin/integration/integration_config.php', ['admin']],
        'admin/integration-config-save' => ['admin/integration/integration_config_save.php', ['admin']],
        'admin/integration-test' => ['admin/integration/integration_test.php', ['admin']],
        'admin/integration-push-catalog' => ['admin/integration/integration_push_catalog.php', ['admin']],
        'admin/integration-retry-orders' => ['admin/integration/integration_retry_orders.php', ['admin']],
        'admin/integration-audit-list' => ['admin/integration/integration_audit_list.php', ['admin']],
        'admin/integration-audit-clear' => ['admin/integration/integration_audit_clear.php', ['admin']],

        // 患者管理
        'patients/list' => ['patients/patient/patient_list.php', ['admin', 'doctor', 'nurse']],
        'patients/get' => ['patients/patient/patient_get.php', ['admin', 'doctor', 'nurse']],
        'patients/save' => ['patients/patient/patient_save.php', ['admin', 'doctor']],
        'patients/discharge' => ['patients/patient/patient_discharge.php', ['admin', 'doctor']],
        'patients/beds' => ['patients/bed/bed_list.php', ['admin', 'doctor']],

        // 病历
        'emr/save' => ['emr/station/save.php', ['admin', 'doctor']],
        'emr/list' => ['emr/station/list.php', ['admin', 'doctor', 'nurse']],
        'emr/get' => ['emr/station/get.php', ['admin', 'doctor', 'nurse']],
        'emr/icd-search' => ['emr/diagnosis/icd_search.php', ['admin', 'doctor', 'nurse', 'pharmacist', 'lab_tech']],
        'emr/template-list' => ['emr/template/template_list.php', ['admin', 'doctor']],
        'emr/template-get' => ['emr/template/template_get.php', ['admin', 'doctor']],
        'emr/template-save' => ['emr/template/template_save.php', ['admin', 'doctor']],
        'emr/template-use' => ['emr/template/template_use.php', ['admin', 'doctor']],
        'emr/template-delete' => ['emr/template/template_delete.php', ['admin', 'doctor']],
        'emr/template-publish' => ['emr/template/template_publish.php', ['admin', 'doctor']],
        'emr/template-review' => ['emr/template/template_review.php', ['admin']],
        'emr/content-list' => ['emr/content/content_list.php', ['admin', 'doctor', 'nurse']],
        'emr/content-save' => ['emr/content/content_save.php', ['admin', 'doctor']],
        'emr/content-delete' => ['emr/content/content_delete.php', ['admin', 'doctor']],
        'emr/content-publish' => ['emr/content/content_publish.php', ['admin', 'doctor']],
        'emr/content-review' => ['emr/content/content_review.php', ['admin']],

        // 药房
        'pharmacy/medication-list' => ['pharmacy/medication/medication_list.php', ['admin', 'pharmacist', 'doctor']],
        'pharmacy/drug-search' => ['pharmacy/medication/drug_search.php', ['admin', 'doctor', 'pharmacist']],
        'pharmacy/medication-save' => ['pharmacy/medication/medication_save.php', ['admin', 'pharmacist']],
        'pharmacy/pending-queue' => ['pharmacy/queue/pending_queue.php', ['admin', 'pharmacist']],
        'pharmacy/dispensing-save' => ['pharmacy/dispensing/dispensing_save.php', ['admin', 'pharmacist']],
        'pharmacy/dispensing-list' => ['pharmacy/dispensing/dispensing_list.php', ['admin', 'pharmacist']],

        // 处方 (药品项目库来自门诊一体化)
        'rx/catalog' => ['rx/rx_catalog.php', ['admin', 'doctor', 'pharmacist']],

        // 护士
        'nurse/patient-list' => ['nurse/patient_list.php', ['admin', 'nurse']],
        'nurse/order-list' => ['nurse/order_list.php', ['admin', 'nurse']],
        'nurse/nursing-action' => ['nurse/nursing_action.php', ['admin', 'nurse']],
        'nurse/vitals-save' => ['nurse/vitals_save.php', ['admin', 'nurse']],
        'nurse/vitals-list' => ['nurse/vitals_list.php', ['admin', 'nurse']],

        // 医嘱
        'orders/save' => ['orders/order/order_save.php', ['admin', 'doctor']],
        'orders/list' => ['orders/order/order_list.php', ['admin', 'doctor', 'nurse']],
        'orders/update' => ['orders/order/order_update.php', ['admin', 'doctor']],
        'orders/group-add' => ['orders/order/group_add.php', ['admin', 'doctor']],
        'orders/quick-search' => ['orders/order/quick_search.php', ['admin', 'doctor']],
        'orders/verify' => ['orders/order/order_verify.php', ['admin', 'doctor', 'nurse']],
        'orders/status' => ['orders/order/order_status.php', ['admin', 'doctor', 'nurse']],
        'orders/submit' => ['orders/order/order_submit.php', ['admin', 'doctor']],
        'orders/copy' => ['orders/order/order_copy.php', ['admin', 'doctor']],
        'orders/invalidate' => ['orders/order/order_invalidate.php', ['admin', 'doctor']],
        'orders/stop' => ['orders/order/order_stop.php', ['admin', 'doctor']],
        'orders/rx-list' => ['orders/rx/rx_list.php', ['admin', 'doctor']],
        'orders/rx-void' => ['orders/rx/rx_void.php', ['admin', 'doctor']],
        'orders/batch-delete' => ['orders/order/order_batch_delete.php', ['admin', 'doctor']],
        'orders/set-list' => ['orders/set/set_list.php', ['admin', 'doctor']],
        'orders/set-save' => ['orders/set/set_save.php', ['admin', 'doctor']],
        'orders/set-apply' => ['orders/set/set_apply.php', ['admin', 'doctor']],

        // 病历 (结构化)
        'emr/category-list' => ['emr/station/category_list.php', ['admin', 'doctor']],
        'emr/category-save' => ['emr/station/category_save.php', ['admin']],
        'emr/document-list' => ['emr/document/document_list.php', ['admin', 'doctor']],
        'emr/document-get' => ['emr/document/document_get.php', ['admin', 'doctor']],
        'emr/document-save' => ['emr/document/document_save.php', ['admin', 'doctor']],
        'emr/document-sign' => ['emr/document/document_sign.php', ['admin', 'doctor']],
        'emr/document-delete' => ['emr/document/document_delete.php', ['admin', 'doctor']],
        'emr/field-values' => ['emr/document/field_values.php', ['admin', 'doctor', 'nurse']],
        'emr/dept-doctors' => ['emr/station/dept_doctors.php', ['admin', 'doctor', 'nurse']],
        'emr/discharge-check' => ['emr/station/discharge_check.php', ['admin', 'doctor']],
        'emr/qc-status' => ['emr/station/qc_status.php', ['admin', 'doctor', 'nurse']],
        'emr/discharge' => ['emr/station/discharge.php', ['admin', 'doctor']],
        'emr/version-list' => ['emr/document/version_list.php', ['admin', 'doctor']],
        'emr/version-restore' => ['emr/document/version_restore.php', ['admin', 'doctor']],

        // 诊断 (患者诊断明细 + 常用诊断模板)
        'emr/diagnosis-list' => ['emr/diagnosis/diagnosis_list.php', ['admin', 'doctor', 'nurse']],
        'emr/diagnosis-save' => ['emr/diagnosis/diagnosis_save.php', ['admin', 'doctor']],
        'emr/diagnosis-delete' => ['emr/diagnosis/diagnosis_delete.php', ['admin', 'doctor']],
        'emr/diagnosis-template-list' => ['emr/diagnosis/diagnosis_template_list.php', ['admin', 'doctor']],
        'emr/diagnosis-template-save' => ['emr/diagnosis/diagnosis_template_save.php', ['admin', 'doctor']],
        'emr/diagnosis-template-delete' => ['emr/diagnosis/diagnosis_template_delete.php', ['admin', 'doctor']],
        'emr/diagnosis-match-get' => ['emr/diagnosis/diagnosis_match_get.php', ['admin', 'doctor', 'nurse']],
        'emr/diagnosis-match-save' => ['emr/diagnosis/diagnosis_match_save.php', ['admin', 'doctor']],
        'emr/diagnosis-sync' => ['emr/diagnosis/diagnosis_sync.php', ['admin', 'doctor']],

        // 患者上下文
        'patients/incoming' => ['patients/incoming/incoming_queue.php', ['admin', 'doctor']],
        'patients/assign' => ['patients/patient/patient_assign.php', ['admin', 'doctor']],
        'patients/assignment' => ['patients/incoming/assignment_get.php', ['admin', 'doctor']],
        'patients/my-patients' => ['patients/incoming/my_patients.php', ['admin', 'doctor']],
        'patients/by-department' => ['patients/incoming/by_department.php', ['admin', 'doctor']],
        'patients/funds' => ['patients/patient/patient_funds.php', ['admin', 'doctor', 'nurse', 'pharmacist']],

        // 医生科室授权
        'doctors/departments' => ['doctors/doctor_departments.php', ['admin', 'doctor']],
        'doctors/doctor-list' => ['doctors/doctor_list.php', ['admin', 'doctor']],

        // 检验
        'lab/save' => ['lab/order/lab_save.php', ['admin', 'doctor']],
        'lab/list' => ['lab/order/lab_list.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'lab/detail' => ['lab/order/lab_detail.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'lab/collect' => ['lab/order/lab_collect.php', ['admin', 'nurse']],
        'lab/result' => ['lab/order/lab_result.php', ['admin', 'lab_tech']],
        'lab/catalog' => ['lab/catalog/lab_catalog.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'lab/panel-list' => ['lab/catalog/lab_panel_list.php', ['admin', 'doctor']],
        'lab/panel-save' => ['lab/catalog/lab_panel_save.php', ['admin', 'doctor']],
        'lab/panel-delete' => ['lab/catalog/lab_panel_delete.php', ['admin', 'doctor']],
        'lab/apply' => ['lab/order/lab_apply.php', ['admin', 'doctor']],
        'lab/delete' => ['lab/order/lab_delete.php', ['admin', 'doctor']],

        // 检查
        'exam/save' => ['exam/report/exam_save.php', ['admin', 'doctor']],
        'exam/list' => ['exam/report/exam_list.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'exam/detail' => ['exam/report/exam_detail.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'exam/report' => ['exam/report/exam_report.php', ['admin', 'lab_tech']],
        'exam/catalog' => ['exam/catalog/exam_catalog.php', ['admin', 'doctor', 'nurse', 'lab_tech']],
        'exam/apply' => ['exam/report/exam_apply.php', ['admin', 'doctor']],
        'exam/delete' => ['exam/report/exam_delete.php', ['admin', 'doctor']],

        // 会诊
        'consult/save' => ['consult/consult_save.php', ['admin', 'doctor']],
        'consult/list' => ['consult/consult_list.php', ['admin', 'doctor']],
        'consult/reply' => ['consult/consult_reply.php', ['admin', 'doctor']],
        'consult/delete' => ['consult/consult_delete.php', ['admin', 'doctor']],

        // 病案首页
        'case/get' => ['case_front_page/get.php', ['admin', 'doctor']],
        'case/save' => ['case_front_page/save.php', ['admin', 'doctor']],
        'case/autofill' => ['case_front_page/autofill.php', ['admin', 'doctor']],
        'case/surgery-save' => ['case_front_page/surgery_save.php', ['admin', 'doctor']],
        'case/surgery-list' => ['case_front_page/surgery_list.php', ['admin', 'doctor']],

        // 门诊病历查询
        'outpatient/search' => ['outpatient/outpatient_search.php', ['admin', 'doctor']],
        'outpatient/mock-data' => ['outpatient/outpatient_mock_data.php', []],

        // 门诊互通 (一期: 只读) - 患者记录/检验结果/检查报告 + PACS 影像
        'outpatient/records' => ['outpatient/outpatient_records.php', ['admin', 'doctor', 'nurse']],
        'outpatient/labs' => ['outpatient/outpatient_labs.php', ['admin', 'doctor', 'nurse']],
        'outpatient/exams' => ['outpatient/outpatient_exams.php', ['admin', 'doctor', 'nurse']],
    ];
}
