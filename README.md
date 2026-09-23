# Hospital Inpatient Information System (HIS)

医院住院一体化系统，覆盖入院 → 病历 → 医嘱 → 摆药发药 → 护士执行 → 检验检查 → 出院/病案首页全闭环。

![version](https://img.shields.io/badge/version-0.1.0-blue)

## 技术栈

- **后端**: PHP 7.x，无框架
- **数据库**: SQLite（默认） / MySQL / PostgreSQL
- **前端**: 原生 JS/CSS SPA
- **富文本**: Quill.js (CDN)
- **PDF**: html2pdf.js (CDN)

## 运行

```bash
npm run setup       # 初始化数据库
npm run import-icd10  # 导入 ICD-10 编码
npm run start       # 启动开发服务器 (127.0.0.1:8080)
```

## 目录结构

```
├── router.php              # 路由入口
├── start.sh                # 启动脚本
├── package.json            # npm 脚本
├── AGENT.md                # 开发规范
├── docs/CHANGELOG.md       # 变更日志
├── data/                   # 运行时数据 (hospital.sqlite, icd10.sqlite)
├── api/                    # 后端 API
│   ├── index.php           # 统一路由入口
│   ├── config.php          # 配置
│   ├── core/               # 核心类
│   ├── migrations/         # 数据库迁移
│   ├── modules/            # 接口实现
│   └── scripts/            # CLI 脚本
└── public/                 # 前端
    ├── index.html          # 唯一入口
    ├── css/                # 样式
    └── js/                 # JS
```

## 角色

- `admin` - 后台管理
- `doctor` - 医生工作站
- `nurse` - 护士工作站
- `pharmacist` - 药房工作站
- `lab_tech` - 检验/检查工作站
