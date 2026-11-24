# 开发工作流规范

## 自动化步骤
每次功能修改必须执行：

1. 递增版本号，三处同步：README.md 顶部徽章 + `api/config.php` APP_VERSION + `package.json`
2. 在 `docs/CHANGELOG.md` 顶部按既有格式新增条目（新增/修复/变更/移除/安全）
3. **本地服务器检查（仅本地测试模式，按需执行）**：默认在线部署模式（标记文件 `.agent-local-server.conf` 不存在或为 `enabled=false`）**不检测、不启动**本地服务器；仅当标记文件为 `enabled=true` 时，才在改动收尾后执行 `npm run server`（或 `bash tools/server.sh`），检测 9095 端口 PHP 服务器，不存在则自动启动
4. README 功能/目录/运行方式变化需同步更新
5. 提交规范（Conventional Commits + 中文）：`<type>: <中文简述>`，type ∈ feat/fix/docs/style/refactor/perf/chore；空一行后附详细正文
6. **一处改动 = 一次提交, 且每个小的优化/修复必须单独 commit**：改完一处立即 `git add -A && git commit`; 严禁把多个不相关的修复/优化捆绑在一次提交里, 便于维护与问题排查 (提交信息按 Conventional Commits + 中文)
7. 大重构提升次版本（0.6.x → 0.7.0）
8. **禁止提交运行时数据**：`data/`、`public/uploads/`、`.vly-run/` 在 .gitignore

## 代码风格
- 单文件 ≤200 行，职责单一
- 类名 PascalCase，文件名 snake_case
- 公共字典统一维护（前端 `Format`、后端 helpers）
- **全站禁止 emoji 图标**（如 🏥 🛠 ⚠ ✕ ⋯ ▾ ▸ 等），统一使用 PHP 绘制的自绘 SVG（`public/icons/`）：
  - 图标按功能分类存放于多级目录：`public/icons/{分类}/{功能}.php`（现有 `common/arrows.php`、`common/actions.php`、`common/status.php`、`medical/objects.php`），每个文件返回 `[名称 => 路径串 或 ['paths'=>..,'stroke_width'=>..]]`，24×24 线稿、`stroke="currentColor"`
  - 页面/片段调用：`require_once __DIR__ . '/../../icons/icon.php';` 后 `<?= icon('common/arrows', 'caret-right', 12) ?>`（大小、class、附加属性可选）
  - JS 动态渲染：页面已引入 `<script src="/icons/js.php"></script>`，统一用 `svgIcon('common/actions/more', 16)`（SVG 仍由 PHP 绘制）
  - 新增图标一律加入对应分类文件（分类可按功能继续细分目录），禁止在页面内散落内联 SVG；键盘按键提示（↑↓ Enter Esc）可用文本
- 数据库迁移使用 PHP Migration 类 (`api/migrations/php/0xx_*.php`)
- 接口与页面分离

## 目录组织铁律（多级目录化，每次维护代码必须遵守）

> 2026-11 目录重构确立。任何新增/移动代码必须放入下述对应层级子目录；
> **禁止在既有目录根部堆放跨功能文件，禁止新建"大杂烩"目录**。

### 后端 api/（按「技术分层 → 业务域 → 功能特性」三级组织）
- `api/core/` 框架核心按技术分层子目录，根级只保留装配器 `bootstrap.php`：
  `http/`(响应/校验) `auth/`(会话认证) `db/`(连接/基础设施配置库/字典库) `cache/`
  `schema/`(迁移/守卫) `site/`(品牌) `dict/` `emr/`(模板定义/守卫) `print/`(条码/快照)。
- `api/core/helpers/` 按业务域子目录：`time/ string/ id/ patient/ org/(科室/数据范围)
  fee/ order/ pharmacy/ emr/ notify/ audit/ cache/ opd/(门诊互通, 内含 client/ 为 FHIR
  客户端栈)`。新增 helper 必须归入对应域；无合适域则**新建域目录**并在
  `bootstrap.php` 对应分组登记，禁止直接丢在 helpers/ 根部。
- `api/modules/{module}/{feature}/` 业务模块二级目录化：模块目录下按功能特性建
  子目录（如 `nurse/` 下 `admission/ bed/ patient/ order/ vitals/ docs/ print/ charge/`），
  特性目录内文件名去掉模块/特性冗余前缀（如 `nurse/vitals/save.php`）。
  单一功能的小模块（如 `consult/`）可保持扁平，但文件间必须是同一功能。
- `api/routes.php` 的 handler 路径必须与文件实际位置一致；route key（对外 URL）
  已发布不得随意变更；新增接口 = 路由表注册 + 对应 `{module}/{feature}/` 文件。
- `api/scripts/` 按用途子目录：`schema/`(安装/迁移/时区) `seed/`(演示数据)
  `dictionary/`(字典/模板导入导出) `jobs/`(定时/推送任务) `diag/`(网络诊断)。
- `api/migrations/php/` **保持平铺编号**（迁移器按文件名排序执行，禁止子目录化）。
- 新建任何文件前先确认归属目录；目录结构变更必须同步 `routes.php`、`bootstrap.php`
  与所有 `require` 相对路径。

### 前端 public/（页面入口扁平 + 组件归类子目录）
- `public/js/{module}/` 根级只放 SPA 页面入口 `{page}.php`（`AjaxLoader.loadPage`
  契约 `/js/{module}/{page}.php`，页面 id 同时决定 `window.{module}_{page}_init`
  初始化函数名与 hash 路由，不得随意改名）；页面的局部片段、组件放入
  `{module}/{feature}/`（如 `nurse/station/bed_panel.php`、`emr/station/init/`），
  模块内多页面共用的小工具放入 `{module}/lib/`。
- `public/js/core/` 公共前端库分层：根级只保留应用外壳（`app/ajax/auth/dom/format/
  notify/branding/pwa/scroll_load/components`），其余按职责子目录：
  `ui/`(通用交互组件) `picker/`(业务选择器) `patient/`(患者上下文) `print/`(打印体系)
  `pdf/` `dict/`。新增公共模块必须归入对应子目录，禁止丢在 core/ 根部。
- `public/css/` 按角色/工作台分目录；`public/icons/` 按 `{分类}/{功能}.php` 组织。
- 静态资源一律在 `public/assets.php` 注册（自动防缓存），禁止页面散写 `<script src>`。
- 打印能力只存在于 `public/js/core/print/print_preview.js`（`tools/lint/print-architecture.php` 强制校验）。

## 运行模式与本地测试服务器（可选）
- 本项目以**在线服务器部署为主**，本地不强制安装 PHP / FrankenPHP；是否启用本地
  PHP 测试服务器由「首次询问」决定，并记录在本机标记文件 `.agent-local-server.conf`
  （项目根目录，只记本机偏好，**严禁提交**；内容示例：`enabled=true` / `enabled=false`）。
- **首次接手（标记文件不存在）**：先探测运行环境（macOS / Linux / Windows、是否已有
  php 或 frankenphp），然后**询问用户是否启用本地 PHP 测试服务器**；用户明确答复前
  不启动、不检测、不安装任何运行时。
- 用户选择启用后，按运行环境自动选择运行时（提问时一并向用户说明可选命令）：

  | 环境 | 首选 | 备选 |
  | --- | --- | --- |
  | macOS | 现有单文件二进制 `~/.local/bin/frankenphp` | `brew install dunglas/frankenphp/frankenphp`；官方脚本 `curl https://frankenphp.dev/install.sh \| sh`；已装系统 php 用 `php -S 127.0.0.1:9095 router.php` |
  | Linux | 系统 `php` 可用时 `php -S 127.0.0.1:9095 router.php` | 官方脚本 `curl https://frankenphp.dev/install.sh \| sh`；或 `brew install dunglas/frankenphp/frankenphp` |
  | Windows | 系统 `php.exe` 可用时 `php -S 127.0.0.1:9095 router.php` | PowerShell `irm https://frankenphp.dev/install.ps1 \| iex`；或官方的 Windows 免安装包（GitHub Releases，内含 PHP 二进制） |

- **端口固定 9095（约定，不得变更）**：`http://0.0.0.0:9095`
- 启用后的启动 / 检查：
  - `npm run dev` 启动: `frankenphp php-server --root public/ --listen 0.0.0.0:9095`
    （或 `bash start.sh`，自动选择系统 php 或 frankenphp）
  - `npm run server` 检测 9095 服务器，不存在则自动启动（仅本地测试模式收尾时执行）
- 语法检查（任何模式均可用，不依赖本地服务器）：系统 php 在位时
  `php tools/lint/php-lint.php`；否则 `~/.local/bin/frankenphp php-cli tools/lint/php-lint.php`
  （即 `npm run lint`）
- **CI 自动自检（GitHub Actions）**：`.github/workflows/ci.yml` 在 push (main) / PR /
  手动触发时执行 —— PHP 8.1~8.5 五版本矩阵语法自检（`php tools/lint/php-lint.php --ci`）
  + 打印架构检查，运行结束在 Run Summary 输出多版本汇总报告；推送前必须先本地
  `npm run lint` 通过，CI 红灯禁止合入

## 时间与时区铁律（三系统统一契约：HIS / 门诊 / PACS）

- **存储一律标准 UTC**：写入唯一来源 `TimeHelper::now()` / `DateHelper::now()`
  （模式感知；`config.db` `time.storage`，local 仅存量兼容过渡）；全套助手在
  `api/core/helpers/time/time_helper.php`。
- **显示按站点时区**：内部 API 响应经 `Response` 输出漏斗
  `TimeHelper::localize()` 自动换算 `*_at/_time` 键（业务日期 `*_date` 不转换；
  `planned_at` 等业务本地时间豁免）；前端零改动。
- **对外接口时间为标准 UTC**（`TimeHelper::isoUtc()`，ISO 8601 带 Z），不受本
  系统时区设置影响；入站外部时间经 `fromUtc()` 按 UTC 解析落库。
- **业务日期时间字段**（长期医嘱 `start_date`、会诊 `planned_at` 等）按「本地
  墙钟」存取、展示原样，不随存储模式换算；分日归属统一
  `TimeHelper::today()/localDate()/dayRange()`。
- **时区设置**：初始化向导（新装）或 系统设置 → 时间与时区（现有部署），
  存 `config.db`（独立于业务库）；业务日期列与快照类单据不受影响。
- **禁止**：`NOW()`/`CURRENT_TIMESTAMP`/`date()` 直写存储时间列；新增表时间列
  不设数据库默认时间（插入显式传 `DateHelper::now()`）。
- **存量库 UTC 化**：`php api/scripts/schema/tz_migrate.php plan|convert|rollback`
  （整库自动备份 + JSON 清单 + `--db=` 副本演练）；升级后自检对照见 CHANGELOG。

## 数据库迁移规范
- 使用 `api/migrations/php/` 中的 PHP 迁移文件 (001_core.php, 002_department.php, ...)
- 每个迁移文件返回数组: `version`, `description`, `up` (回调函数)
- 使用 `Migration` 类抽象 SQLite/MySQL/PostgreSQL 差异
- `setup.php` 执行所有迁移, `migrate.php` 执行未完成的迁移
- 旧的 SQL 文件已弃用, 仅作参考

## 新接口约定
1. 在 `api/routes.php` 路由表注册 (getRoutes 数组) →
2. 实现文件放 `api/modules/{module}/{feature}/`（见「目录组织铁律」）→
3. 前端页放 `public/js/{module}/{page}.php` 并在对应 sidebar 加入入口
   (页面片段统一使用 `.php` 扩展名, 首行 `<?php header("Content-Type: text/html; charset=utf-8"); ?>`)

## 前端页面约定
- 唯一入口: `public/index.php` (SPA 壳 + /api/ 路由)
- **静态资源统一管理**: 所有 JS/CSS 在 `public/assets.php` 的 `Asset::$css` / `Asset::$js` 注册, 页面用 `Asset::css()` / `Asset::js()` 输出 (自动带 APP_VERSION 防缓存); 新增/移除资源只改 assets.php, 禁止在页面里散写 `<script src>` / `<link>`
- **弹窗/面板内长表格统一固定表头组件**: 优先调用 `Dom.scrollTable(head, body, opts)` (js/core/dom.js) ——
  默认输出 `<div class="table-scroll"><table class="table fix-head">` (自带 52vh 限高滚动区, 弹窗内列表用);
  `opts.wrap === false` 输出裸表格由外层滚动容器滚动 (床位面板 tab 等); `opts.maxHeight` 覆盖限高;
  `opts.tableClass` 替换表格类 (如 `'temp-grid'`)。
  手写模板时的等价原语: 表格加 `.fix-head` (表头 sticky 吸附于最近滚动容器顶部) + 外层 `.table-scroll`。
  **禁止**再写内联 `overflow-x:auto` / `max-height` 滚动包装 —— 它会形成新的滚动容器, 使表头吸附失效;
  页面级滚动容器 (如护士站面板 `.nurse-op-body`) 保持双向 `overflow: auto`, 表格直接作为其子元素
- 页面片段: `public/js/{module}/{page}.php`, 通过 `AjaxLoader.loadPage()` 动态加载
- 侧边栏: `public/js/shared/sidebar_{role}.php`
- 无独立 HTML 文件, 全部统一为 PHP
- **路由导航统一使用全局 `navigateTo` (app.js)**: 页面片段内禁止再定义 `navigateTo`, 避免覆盖 hash 路由导致布局/URL 不同步

## 移动端铁律（长期支持，新增/修改页面必须遵守）

- **布局断点**：`≤768px` 为移动端（抽屉侧边栏 + 纵向布局），`>768px` 为桌面（侧边栏可折叠窄栏）。
- **侧边栏**：切换按钮 `#sidebar-toggle`（topbar 左侧）调 `App.toggleSidebar()`；桌面折叠为 `sidebar-mini`（64px 仅图标，状态存 `localStorage his_sidebar_mini`）；移动端默认隐藏（`translateX(-100%)`），点按钮加 `open` 滑出 + `#sidebar-mask` 遮罩，点菜单项自动收起（`App.initMobile()`）。
- **页面布局**：表格容器要能横向滚动（`.table { min-width: 640px }` 于移动媒体查询）；筛选条控件全宽换行；`.form-grid` 降单列；`.pane-wrap` 左右两栏移动端纵向堆叠；统计卡 ≤2 列；日志/状态/审核行允许换行。
- **移动端样式只写在 `doctor.css`（框架）与 `admin.css`（管理端）的 `@media (max-width: 768px)` 块内**，禁止页面内散写移动样式。
- **新增页面默认移动可用**：走公共布局类（`.page-head/.card/.table/.form-grid/.pane-wrap`）即可获得移动适配；禁止用固定 px 宽度撑破小屏。
