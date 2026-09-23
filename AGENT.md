# 开发工作流规范

## 自动化步骤
每次功能修改必须执行：

1. 递增版本号，三处同步：README.md 顶部徽章 + `api/config.php` APP_VERSION + `package.json`
2. 在 `docs/CHANGELOG.md` 顶部按既有格式新增条目（新增/修复/变更/移除/安全）
3. README 功能/目录/运行方式变化需同步更新
4. 提交规范（Conventional Commits + 中文）：`<type>: <中文简述>`，type ∈ feat/fix/docs/style/refactor/perf/chore；空一行后附详细正文
5. **一处改动 = 一次提交**：改完一处立即 `git add -A && git commit`
6. 大重构提升次版本（0.6.x → 0.7.0）
7. **禁止提交运行时数据**：`data/`、`public/uploads/`、`.vly-run/` 在 .gitignore

## 代码风格
- 单文件 ≤200 行，职责单一
- 类名 PascalCase，文件名 snake_case
- 公共字典统一维护（前端 `Format`、后端 helpers）
- 数据库分散迁移
- 接口与页面分离

## 新接口约定
1. 在 `api/index.php` 路由表注册 →
2. 实现文件放 `api/modules/{module}/` →
3. 前端页放 `public/js/{module}/{page}.html` 并在对应 sidebar 加入入口
