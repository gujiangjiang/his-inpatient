# Hospital Inpatient Information System (HIS)

医院住院一体化系统，覆盖入院 → 病历 → 医嘱 → 摆药发药 → 护士执行 → 检验检查 → 出院/病案首页全闭环。

![version](https://img.shields.io/badge/version-0.12.0-blue)

## 技术栈

- **后端**: PHP 7.x，无框架
- **数据库**: SQLite（默认） / MySQL / PostgreSQL
- **前端**: 原生 JS/CSS SPA
- **富文本**: Quill.js (本地优先 / CDN fallback)
- **PDF**: html2pdf.js (本地优先 / CDN fallback)

## 内网部署

在无外网访问的内网环境中部署前，先下载前端依赖库到本地：

```bash
npm run download-libs   # 下载 Quill.js + html2pdf.js 到 public/lib/
```

下载完成后，应用将优先使用本地文件；若本地文件缺失或加载失败，自动回退为 CDN。

## 快速开始

**全自动初始化** (下载依赖 + 初始化数据库 + 导入 ICD-10 + 生成演示数据):

```bash
npm run init            # 下载 libs + 初始化数据库 + 导入 ICD-10 + 生成演示数据
npm run start           # 启动开发服务器 (http://127.0.0.1:8000)
```

**本地开发 (FrankenPHP)** (推荐用于本地测试):

```bash
npm run dev             # 启动 FrankenPHP 服务器 (http://0.0.0.0:9090)
# 或指定端口: PORT=8888 npm run dev
```

**手动初始化**:

```bash
npm run download-libs   # (可选) 下载前端依赖库到本地用于内网部署
npm run setup           # 初始化数据库
npm run import-icd10    # 导入 ICD-10 编码
npm run seed            # 生成演示数据
npm start               # 启动服务器
```

**语法检查** (无需系统 PHP):

```bash
npm run lint            # 或: ~/.local/bin/frankenphp php-cli tools/lint/php-lint.php
```

## 目录结构

```
├── router.php              # 路由入口 (SPA fallback + API 代理)
├── start.sh                # 启动脚本
├── package.json            # npm 脚本
├── AGENT.md                # 开发规范
├── docs/CHANGELOG.md       # 变更日志
├── data/                   # 运行时数据 (hospital.sqlite, icd10.sqlite) [.gitignore]
├── api/                    # 后端 API
│   ├── index.php           # 统一路由入口
│   ├── config.php          # 配置 (APP_VERSION, DB 驱动, Cookie 策略)
│   ├── core/               # 核心类 (DB, Auth, Response, Validator, SetupGuard)
│   ├── migrations/         # 三方言迁移 (sqlite/mysql/postgresql)
│   ├── modules/            # 接口实现 (9 个模块)
│   └── scripts/            # CLI 脚本 (setup, import_icd10, seed, migrate, download_libs)
└── public/                 # 前端 (Web 根目录)
    ├── index.php           # 唯一入口 (SPA 壳 + /api/ 路由)
    ├── lib/                # 第三方库 (Quill.js, html2pdf.js) - 由 download-libs 生成
    ├── css/                # 样式 (core, login, doctor)
    ├── js/                 # JS + PHP 页面片段
    │   ├── core/           # 公共单例 (Format/AjaxLoader/Auth/Dom/...)
    │   ├── login/          # 登录逻辑
    │   ├── shared/         # 各角色 sidebar (PHP)
    │   └── {module}/       # 各工作站页面片段 (PHP)
```

## 角色

- `admin` - 后台管理
- `doctor` - 医生工作站
- `nurse` - 护士工作站
- `pharmacist` - 药房工作站
- `lab_tech` - 检验/检查工作站

### 演示账号

默认通过 `npm run seed` 创建 (密码均为 `123456`):

- `doctor1` / `doctor2` - 医生
- `nurse1` / `nurse2` - 护士
- `pharma1` - 药师
- `labtech1` - 校验技师
- 管理员账号通过初始化向导创建
