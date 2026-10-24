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
