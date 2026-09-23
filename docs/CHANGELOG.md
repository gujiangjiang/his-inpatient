# 变更日志

所有重要变更记录在本文件中。

格式参照 [语义化版本](https://semver.org/)。

## [0.6.0] - 2026-09-23

### 新增
- **M2**: ICD-10 独立库导入 (21 章节 + 79 条常见分类 + FTS5)
- **M2**: EMR 模块：save/list/get + template_list/get/save/use/delete + icd_search
- **M2**: Quill.js 富文本编辑器封装 (RichEditor) + textarea 降级方案
- **M2**: TemplatePicker 共用模板选择弹窗
- **M2**: IcdSearch 防抖自动补全
- **M3**: 药房模块：medication_list/save, pending_queue, dispensing_save/list
- **M3**: 护士模块：patient_list, order_list, nursing_action, vitals_save/list
- **M3**: 医嘱模块：order_save (含成组子医嘱事务), order_list (树形分页), order_verify, order_status
- **M4**: 检验模块：lab_save/list/detail/collect/result
- **M4**: 检查模块：exam_save/list/detail/report
- **M4**: 病案首页模块：case get/save/autofill/surgery_save/surgery_list
- **M4**: 外部门诊接口：outpatient_search (实时/mock 回退)
- **M5**: 初始化向导 (setup_status + setup)
- **M5**: seed_demo.php + seed_demo_patients.php (幂等演示数据)
- **M6**: 子医嘱级联状态机 + Format.escape XSS 防护 + migrate.php 增量迁移
- 前端 SPA 框架：index.html 加载器, login.js 哈希路由, sidebar 共享组件
- admin/doctor/nurse/pharmacy/lab 各工作站页面

### 修复
- 修复 lab_result 表单字段命名 (使用 data 属性替代 bracket 语法)
- 修复 patient_list ward 选项 d.id → w.id
- 修复 seed 脚本 fetchColumn 用法
- 修复 patient_save patient_no 生成

## [0.1.0] - 2026-09-23

### 新增
- 项目骨架：router.php 路由入口，start.sh 启动脚本，package.json npm 脚本
- 数据库核心类：DB(PDO 单例)、DateHelper、ArrayHelper、StringHelper、UploadHelper
- API 统一入口：index.php 路由分发，config.php 配置，auth.php 认证，response.php 响应
- 初始化守卫：setup_guard.php（未初始化阻断业务接口返回 503 SETUP_REQUIRED）
- 三方言迁移：sqlite.sql / mysql.sql / postgresql.sql
- 初始化脚本：setup.php
