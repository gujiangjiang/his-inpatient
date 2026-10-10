# 开发工作流规范

## 自动化步骤
每次功能修改必须执行：

1. 递增版本号，三处同步：README.md 顶部徽章 + `api/config.php` APP_VERSION + `package.json`
2. 在 `docs/CHANGELOG.md` 顶部按既有格式新增条目（新增/修复/变更/移除/安全）
3. **每次代码修改/优化完成后，必须执行 `npm run server`（或 `bash tools/server.sh`）**：检测 9095 端口 PHP 服务器，不存在则自动启动，确保修改后服务可用
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

## 本地开发
- **端口固定 9095（约定，不得变更）**：`http://0.0.0.0:9095`
- 使用 FrankenPHP (`~/.local/bin/frankenphp`) 作为本地开发服务器
- `npm run dev` 启动: `frankenphp php-server --root public/ --listen 0.0.0.0:9095`
- `npm run server` 检测 9095 服务器，不存在则自动启动（AI 每次改完代码后必须执行）
- `npm run lint` 使用 frankenphp 内置 PHP 检查语法 (无需系统 PHP)

## 数据库迁移规范
- 使用 `api/migrations/php/` 中的 PHP 迁移文件 (001_core.php, 002_department.php, ...)
- 每个迁移文件返回数组: `version`, `description`, `up` (回调函数)
- 使用 `Migration` 类抽象 SQLite/MySQL/PostgreSQL 差异
- `setup.php` 执行所有迁移, `migrate.php` 执行未完成的迁移
- 旧的 SQL 文件已弃用, 仅作参考

## 新接口约定
1. 在 `api/routes.php` 路由表注册 (getRoutes 数组) →
2. 实现文件放 `api/modules/{module}/` →
3. 前端页放 `public/js/{module}/{page}.php` 并在对应 sidebar 加入入口
   (页面片段统一使用 `.php` 扩展名, 首行 `<?php header("Content-Type: text/html; charset=utf-8"); ?>`)

## 前端页面约定
- 唯一入口: `public/index.php` (SPA 壳 + /api/ 路由)
- **静态资源统一管理**: 所有 JS/CSS 在 `public/assets.php` 的 `Asset::$css` / `Asset::$js` 注册, 页面用 `Asset::css()` / `Asset::js()` 输出 (自动带 APP_VERSION 防缓存); 新增/移除资源只改 assets.php, 禁止在页面里散写 `<script src>` / `<link>`
- 页面片段: `public/js/{module}/{page}.php`, 通过 `AjaxLoader.loadPage()` 动态加载
- 侧边栏: `public/js/shared/sidebar_{role}.php`
- 无独立 HTML 文件, 全部统一为 PHP
- **路由导航统一使用全局 `navigateTo` (app.js)**: 页面片段内禁止再定义 `navigateTo`, 避免覆盖 hash 路由导致布局/URL 不同步
