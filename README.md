# Hospital Inpatient Information System (HIS)

医院住院一体化系统，覆盖入院 → 病历 → 医嘱 → 摆药发药 → 护士执行 → 检验检查 → 出院/病案首页全闭环。

![version](https://img.shields.io/badge/version-0.26.0-blue)

## 技术栈

- **后端**: PHP 7.x，无框架
- **数据库**: SQLite（默认） / MySQL / PostgreSQL
- **前端**: 原生 JS/CSS SPA
- **病历编辑器**: 自研字处理编辑器（借鉴 Quire/Word 模型：纯 JSON 文档模型 + 固定抬头/固定标签/填写槽 + 自动分页打印），**全站零第三方前端库**
- **PDF**: 浏览器打印（打印对话框可选「另存为 PDF」，分页与打印件完全一致）

## 核心技术特性

- **结构化医嘱工作站**: 长期/临时医嘱、成组医嘱 (溶媒 + 主药共享用法/滴速)、鼠标旁悬浮快速开嘱 (药品/非药品统一检索, 点击即入列表)、行内点击修改剂量用法、径外/自费/不计价标签、长期全停、医嘱组套 (路径)、快捷键 (F9/F8/F2/F4/F5)
- **病历字处理编辑器（全站唯一模式）**: 类 Word 整页编辑画布（固定抬头/信息栏/内容区，Word「窄」边距），内容区内置不可删除固定元素（`fx` 固定标签 + `slot` 填写槽 + `dyn` 动态项），事务式撤销/重做（打字合并），文档单一 JSON（`doc_json`）存储 + 打印 HTML 快照；**自动分页打印**（页眉医院名+文书名、患者信息栏、页脚第X页/共Y页，正文起点按页眉实测高度排版，段落跨页、表格按行跨页）；内置 **BMI 由身高/体重自动计算**、**初步诊断点击一键同步**（来源诊断模块入院诊断，不可手改）、**医师签名自动填入**（上级在前+本人，不标注上下级）；**字段引用**（病程等文书中自动调用【姓名】【年龄】【主诉】等患者字段）；历史文书/旧模板自动转换为字处理模型；文书版本历史（保存/签名/恢复均落版）；已签名文书服务端锁定，保存按文书守卫/模板校验固定元素一致性
- **患者卡片墙**: 护理等级配色床徽、危重/过敏标记、VTE 风险标签、入院天数与费用一览
- **患者上下文**: 添加/主管/科室患者+/出院多维切换，跨页面 (医嘱/病历/首页) 患者上下文保持
- **门诊一体化互通**: 管理端「接口管理」配置门诊服务地址与 Token；住院医生可只读查看患者门诊记录/检验/检查（FHIR + 业务接口）；支持 HIS 字典推送到门诊；**住院检验/检查开单一律视为已缴费，自动推送门诊一体化系统登记出结果**（失败入队重试，支持手动重推）

## 内网部署

全站前端零第三方依赖（编辑器/打印均为自研），内网离线环境可直接部署，无需下载任何前端库。

## 快速开始

**全自动初始化** (初始化数据库 + 导入 ICD-10 + 生成演示数据):

```bash
npm run init            # 初始化数据库 + 导入 ICD-10 + 生成演示数据
npm run start           # 启动开发服务器 (http://127.0.0.1:8000)
```

**本地开发 (FrankenPHP)** (推荐用于本地测试, 端口固定 9095):

```bash
npm run dev             # 启动 FrankenPHP 服务器 (http://0.0.0.0:9095)
npm run server          # 检测 9095 服务器, 不存在则自动启动 (仅启用本地测试模式时, 见 AGENT.md)
```

**手动初始化**:

```bash
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
├── docs/                   # 文档 (CHANGELOG 变更日志 / DEPLOY 部署指南 / nginx.conf.example 配置示例)
├── data/                   # 运行时数据 (hospital.sqlite, icd10.sqlite) [.gitignore]
├── api/                    # 后端 API
│   ├── index.php           # 统一路由入口
│   ├── config.php          # 配置 (APP_VERSION, DB 驱动, Cookie 策略)
│   ├── core/               # 核心类 (DB, Auth, Response, Validator, SetupGuard)
│   ├── migrations/         # 三方言迁移 (sqlite/mysql/postgresql)
│   ├── modules/            # 接口实现 (9 个模块)
│   └── scripts/            # CLI 脚本 (setup, import_icd10, seed, migrate)
└── public/                 # 前端 (Web 根目录)
    ├── index.php           # 唯一入口 (SPA 壳 + /api/ 路由)
    ├── css/                # 样式 (core, login, doctor)
    ├── js/                 # JS + PHP 页面片段
    │   ├── core/           # 公共单例 (Format/AjaxLoader/Auth/Dom/...)
    │   ├── login/          # 登录逻辑
    │   ├── shared/         # 各角色 sidebar (PHP)
    │   ├── emr_editor/     # 病历字处理编辑器 (模型/历史/操作/渲染/分页/打印/光标/控制器)
    │   ├── orders/         # 医嘱工作站 (order_station/order_editor/drug_search/order_sets)
    │   └── {module}/       # 各工作站页面片段 (PHP)
```

## 角色

- `admin` - 后台管理
- `doctor` - 医生工作站
- `nurse` - 护士工作站
- `pharmacist` - 药房工作站
- `lab_tech` - 检验/检查工作站

- **内置住院病历模板**: 入院记录×4（入院记录/再次或多次入院记录/24小时内入出院记录/24小时内入院死亡记录）、病程记录×3（日常查房/上级医师查房/首次病程）、沟通记录（医患沟通）、出院记录；导出文件见 `docs/emr_templates/`；枚举字段下拉选择；入院记录顶部患者信息 12 项两列表，**编辑状态下与病案首页单向同步**（未手动修改字段自动更新、手动保存后冻结、已签名快照不参与）；种子自动清理旧测试模板

### 演示账号

默认通过 `npm run seed` 创建 (密码均为 `123456`):

- `doctor1` / `doctor2` - 医生
- `nurse1` / `nurse2` - 护士
- `pharma1` - 药师
- `labtech1` - 校验技师
- 管理员账号通过初始化向导创建
