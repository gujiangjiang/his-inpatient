# 变更日志

所有重要变更记录在本文件中。

格式参照 [语义化版本](https://semver.org/)。

## [0.12.2] - 2026-09-23

### 修复
- 患者选择 403: patients/get 权限放开至「本人指派管辖患者」(主管或上级医师), 跨科室指派患者可正常进入
- 布局错乱: 患者信息栏移出 main-container, 改为顶部栏下方独立横向条; #main-app 改 flex 纵向布局, 信息栏显示时主区自动收缩
- 患者切换器改为文字方块列表 (姓名/性别年龄/床号/住院号/主治/诊断), 添加弹窗同款样式

## [0.12.1] - 2026-09-23

### 修复
- 医生开嘱 403: pharmacy/medication-list 角色放宽为 admin/pharmacist/doctor (只读药品目录, 开嘱需检索)

### 变更
- 患者切换器重构: 默认 Tab 为「本人」; 点击「添加」弹出悬浮患者列表弹窗; 「科室」Tab 改为权限科室折叠列表 + 出院患者组
- 新增 patients/by-department 接口 (按科室查在院/出院, 校验医生科室授权, 越权 403)
- 选择患者后顶部栏下方显示患者信息栏 (姓名/性别/年龄/科室/病区/主诊断/总费用占位 + 切换患者按钮)
- 未选患者时信息栏与侧边栏隐藏, 页面中央显示引导占位

## [0.12.0] - 2026-09-23

### 新增 (医生工作站 + 结构化病历引擎)
- **Step1 后端**: 迁移 011 (emr_categories / emr_documents / patient_assignments / doctor_department_permissions + emr_templates 扩展 schema_json/category_id)
- **Step1 API**: category-list/save, document-list/get/save/sign/delete, patients/incoming|assign|assignment|my-patients, doctors/departments|doctor-list
- **签名锁定**: document_save/delete 服务端强制拦截 signed 状态写入/删除 (403)
- **签名校验**: document_sign 需密码二次校验身份, 签名后只读
- **Step2 布局**: 顶部栏(医院名+患者切换器) + 患者上下文锁定 (PatientContext) + 侧边栏显隐 + 未选患者占位图
- **Step3 病历树**: 分类树 + 每分类 + 新建模板 + 右键菜单(未签名:打开/编辑/删除; 已签名:仅打开)
- **Step4 编辑器**: 工具条(编辑切换/保存/签名/撤销重做/打印PDF) + 结构化段落 + 右下角签名栏 + 只读锁定
- **Step5 种子**: 常规入院记录结构化模板 (10 段落, 仿宋A4排版) + 病历分类 + 医师指派/科室授权 + 待入科患者
- 诊断页(患者上下文) / 占位页 / emr.css

### 修复
- 修复 template_list 变量名拼写错误

## [0.11.0] - 2026-09-23

### 变更
- **入口统一**: 删除 `public/index.html`，SPA 壳并入 `public/index.php` 单一入口
- **页面统一为 PHP**: `public/js/**/*.html` 全部转为 `.php` (含 shared/sidebar_*)，前端 fetch 相应更新
- 登录表单补 `class="login-form"` 修复样式错位 (CSS 选择器原为 `.login-form`)

### 新增
- seed_demo.php 增加 `admin` 演示账号 (密码 123456，共 7 个账号)

### 修复
- doctor 默认路由 `doctor/patient_list` → `patients/patient_list` (原指向不存在的页面)

## [0.10.0] - 2026-09-23

### 新增
- FrankenPHP 本地开发支持: `public/index.php` 路由入口 (Caddy try_files fallback)
- `npm run lint` 语法检查工具 (tokenizer, 无需系统 PHP)
- `docs/nginx.conf.example` 生产环境 Nginx 配置参考
- `public/worker.php` FrankenPHP Worker 模式入口 (生产高性能)

### 修复
- 修复 config.php DATA_DIR 重复定义
- 修复 CLI 模式下 session 配置警告
- 修复 Migration 外键 SQL 语法 (兼容 SQLite)
- 修复 auth.php 令牌回退 session 冲突 (session_write_close 后切换)
- 修复 seed_demo_patients.php 未选字段 + 非幂等问题 (幂等化)

### 变更
- start.sh 支持 FrankenPHP / 系统 PHP 双模式
- 版本号 0.9.0 -> 0.10.0

## [0.9.0] - 2026-09-23

### 新增
- **数据库迁移重构**: 统一 PHP 迁移系统 (Migration 类), 支持 SQLite/MySQL/PostgreSQL 自动翻译
- 迁移文件按功能拆分: 001_core, 002_department, 003_user, 004_patient, 005_orders, 006_emr, 007_nursing, 008_pharmacy, 009_lab_exam, 010_case_surgery
- `npm run init` 一键初始化 (下载 libs + 数据库 + ICD-10 + 演示数据)
- Migration 类自动处理: 表创建/列检查/索引创建/外键/MIGRATIONS 记录

### 变更
- setup.php 和 migrate.php 现在使用 PHP 迁移系统自动执行
- 旧 SQL 迁移文件标记为已弃用
- 版本号 0.8.0 -> 0.9.0

## [0.8.0] - 2026-09-23

### 新增
- **M7**: 开医嘱页面重写
  - 工具栏: 药品+ / 非药品+ / 提交全部草稿 / 批量删除
  - 实时保存草稿 (status=draft), 提交后才进入 pending 状态
  - Tab 切换: 草稿 / 待核对 / 已核对执行
  - 多选删除草稿 (选中后显示删除按钮)
  - 作废/复制悬浮窗 (点击已提交医嘱)
- **order_submit.php**: 批量提交草稿为待核对
- **order_copy.php**: 复制医嘱 (仅手动开具, 检查药品库存可用性)
- **order_invalidate.php**: 作废医嘱 (需已核对后)
- **order_batch_delete.php**: 批量删除草稿
- `is_auto_generated` 字段 (区分自动生成 vs 手动开具)
- migrate.php 增量添加 `is_auto_generated` 字段

### 变更
- 状态机新增 `invalid` (作废) 状态
- order_list 支持按 status 过滤
- `order_save.php` 草稿默认为 `draft` 而非 `pending`

### 修复
- 修复 order_verify 状态机 (draft 只能到 pending)

## [0.7.0] - 2026-09-23

### 新增
- `npm run download-libs` 下载 Quill.js + html2pdf.js 到本地 `public/lib/`，支持离线内网部署
- `npm run init` 一键初始化 (下载 libs + 初始化数据库 + 导入 ICD-10 + 生成演示数据)
- `public/index.html` 优先加载本地库文件，RichEditor/PdfGenerator 自动降级 (textarea/window.print)
- README 更新部署说明

### 变更
- `.gitignore` 忽略 `public/lib/` (由 download-libs 生成)
- 版本号 0.6.0 -> 0.7.0

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
- 修复 lab_result 查询器 (data 属性替代 bracket 语法)
- 修复 patient_list ward 选项 d.id -> w.id
- 修复 seed 脚本 fetchColumn 用法
- 修复 patient_save patient_no 生成
- 修复前端 init 函数命名不匹配 (patients_patient_list_init, patients_beds_init, orders_order_list_init)
- 修复 AjaxLoader silent 模式不应弹出 toast

### 安全
- RichEditor 封装 Quill.js (禁止直接 new Quill) + textarea 降级
- Format.escape HTML 转义 (121 处调用) 防 XSS
- 所有动态文本经 Format.escape 后写入 innerHTML
- Session dual-channel: X-Session-Id header 回退 (防 iframe/Cookie 拦截)
- ICD-10 搜索接口必须认证

## [0.1.0] - 2026-09-23

### 新增
- 项目骨架：router.php 路由入口，start.sh 启动脚本，package.json npm 脚本
- 数据库核心类：DB(PDO 单例)、DateHelper、ArrayHelper、StringHelper、UploadHelper
- API 统一入口：index.php 路由分发，config.php 配置，auth.php 认证，response.php 响应
- 初始化守卫：setup_guard.php（未初始化阻断业务接口返回 503 SETUP_REQUIRED）
- 三方言迁移：sqlite.sql / mysql.sql / postgresql.sql
- 初始化脚本：setup.php
