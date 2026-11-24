# Hospital Inpatient Information System (HIS)

医院住院一体化系统，覆盖入院 → 病历 → 医嘱 → 摆药发药 → 护士执行 → 检验检查 → 出院/病案首页全闭环。

![version](https://img.shields.io/badge/version-0.64.0-blue)
![CI](https://github.com/gujiangjiang/his-inpatient/actions/workflows/ci.yml/badge.svg)

## 技术栈

- **后端**: PHP 7.x，无框架
- **数据库**: SQLite（默认） / MySQL / PostgreSQL
- **前端**: 原生 JS/CSS SPA
- **病历编辑器**: 自研字处理编辑器（借鉴 Quire/Word 模型：纯 JSON 文档模型 + 固定抬头/固定标签/填写槽 + 自动分页打印），**全站零第三方前端库**

## CI（GitHub Actions）

`.github/workflows/ci.yml` 在 push (main) / PR / 手动触发时运行：

- **PHP 语法自检**: 8.1 / 8.2 / 8.3 / 8.4 / 8.5 五版本矩阵，`token_get_all` 全量扫描 430+ 文件
- **打印架构检查**: 浏览器打印能力仅允许存在于 `PrintPreview`
- **汇总报告**: 运行结束后在 Run Summary 输出 Markdown 汇总（各版本结果矩阵 + 总体结论），每次任务亦单独输出检查文件数/错误数/结论

本地等效命令：`npm run lint`（PHP 语法 + 打印架构，经 FrankenPHP 运行）。
- **PDF**: 浏览器打印（打印对话框可选「另存为 PDF」，分页与打印件完全一致）
- **数据存储**: 主业务库 `data/hospital.sqlite` + 系统配置库 `data/config.db` + **独立基础字典库 `data/dicts.sqlite`**（省市县+邮编 3104 条 / 职业分类大典2022 全量 / 院级字典；**固定字典库 `data/dicts.sqlite` 随仓库提交远程**（种子与构建工具在 `tools/dict_data/` 与 `tools/build_dicts.php`，数据不在 PHP 中），运行时直接使用；管理员「字典管理」维护、修改即时生效）
- **消息推送**: SSE 长连接（非轮询）——审核/维护/待核医嘱等业务事件定向推送（顶栏铃铛 + 未读角标），处理完成自动消失

## 核心技术特性

- **结构化医嘱工作站**: 长期/临时医嘱、成组医嘱 (溶媒 + 主药共享用法/滴速)、鼠标旁悬浮快速开嘱 (药品/非药品统一检索, 点击即入列表)、行内点击修改剂量用法、径外/自费/不计价标签、长期全停、医嘱组套 (路径)、快捷键 (F9/F8/F2/F4/F5)
- **病历字处理编辑器（全站唯一模式）**: 类 Word 整页编辑画布（固定抬头/信息栏/内容区，Word「窄」边距），内容区内置不可删除固定元素（`fx` 固定标签 + `slot` 填写槽 + `dyn` 动态项），事务式撤销/重做（打字合并），文档单一 JSON（`doc_json`）存储 + 打印 HTML 快照；**自动分页打印**（页眉医院名+文书名、患者信息栏、页脚第X页/共Y页，正文起点按页眉实测高度排版，段落跨页、表格按行跨页）；内置 **BMI 由身高/体重自动计算**、**初步诊断点击一键同步**（来源诊断模块入院诊断，不可手改）、**医师签名自动填入**（上级在前+本人，不标注上下级）；**字段引用**（病程等文书中自动调用【姓名】【年龄】【主诉】等患者字段）；历史文书/旧模板自动转换为字处理模型；文书版本历史（保存/签名/恢复均落版）；已签名文书服务端锁定，保存按文书守卫/模板校验固定元素一致性
- **患者卡片墙**: 护理等级配色床徽、危重/过敏标记、VTE 风险标签、入院天数与费用一览
- **护士站床位一览表（主入口）**: 病区 Tabs + 占用统计 + 床位卡片墙；点击床位卡片弹出操作面板集中完成 医嘱核查执行 / 病历只读 / 检验检查 / 摆药 / 体温单 / 护理记录单 / 常规护理文书 / 转床出院；支持病区维度**批量预摆药**与**空闲床位入科登记**
- **护理文书**: 体温单（每日时点体温/脉搏/呼吸/血压/出入量网格）、护理记录单（病情观察/措施/给药/转科流水）、常规护理文书（入院评估/护理计划/健康宣教/转科记录）
- **医疗标签打印**: 250×25mm 患者腕带、50×30mm 检验试管条码（区分未打印/已打印及补打）、60×60mm 药品医嘱执行贴；统一 Code 128 矢量条码与非标纸张预览
- **患者上下文**: 添加/主管/科室患者+/出院多维切换，跨页面 (医嘱/病历/首页) 患者上下文保持
- **统一临床年龄**: 前后端统一采用病案首页口径，满1周岁显示整岁、不足1周岁显示月、新生儿≤28天显示天；患者列表、工作站、病历及全部打印单据共用
- **移动端适配（长期支持）**: 医生站/护士站/医嘱站/病历站/患者卡片墙/临床面板均适配窄屏（≤768px）；病历编辑画布保持 A4 版式，窄屏整体等比缩放（不重排版）
- **统一打印预览与打印**: 电子病历、病案首页、医嘱单、检查/检验申请及报告、会诊单、处方笺、体温单、护理记录单统一走 `PrintDocument + PrintPreview`；自动识别 A4/A5/半 A4，纸张按原始尺寸排版、窄屏仅整体等比缩放，预览与独立打印页共用分页和留白规则；只读文书可隐藏打印按钮
- **不可变打印快照**: 已签名病历、已提交检验/检查/会诊申请、已发布报告及已提交处方在业务事务内生成不可变快照与SHA-256哈希；后续患者、医院、目录或人员资料变化不影响法定打印内容，草稿不生成快照
- **门诊系统互通**: 管理端「接口管理」配置门诊服务地址与 Token；住院医生可只读查看患者门诊记录/检验/检查（FHIR + 业务接口）；支持 HIS 字典推送到门诊；**住院检验/检查开单一律视为已缴费，自动推送门诊系统登记出结果**（失败入队重试，支持手动重推）

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
├── AGENT.md                # 开发规范 (含目录组织铁律)
├── docs/                   # 文档 (CHANGELOG 变更日志 / DEPLOY 部署指南 / PRINTING 打印 / emr_templates 内置模板)
├── data/                   # 运行时数据 (hospital.sqlite, icd10.sqlite) [.gitignore]
├── tools/                  # 工具 (build_dicts 字典构建 / lint 语法与架构检查 / server.sh)
├── api/                    # 后端 API
│   ├── index.php           # 统一路由入口
│   ├── routes.php          # 路由表 (187 条, action → handler)
│   ├── config.php          # 配置 (APP_VERSION, DB 驱动, Cookie 策略)
│   ├── core/               # 框架核心 (按技术分层)
│   │   ├── bootstrap.php   #   装配器 (全量 require 清单)
│   │   ├── http/ auth/ db/ cache/ schema/ site/ dict/ emr/ print/
│   │   └── helpers/        #   业务助手 (按域: time/ patient/ org/ order/ pharmacy/ emr/ opd/ ...)
│   ├── migrations/         # 三方言迁移 (sqlite/mysql/postgresql + php/ 平铺编号迁移)
│   ├── modules/            # 接口实现 ({module}/{feature}/ 二级目录化)
│   │   ├── admin/ audit/ auth/ case_front_page/ consult/ dict/ doctors/ emr/
│   │   ├── exam/ lab/ notify/ nurse/ orders/ outpatient/ patients/
│   │   ├── pharmacy/ rx/ setup/ surgery/
│   │   └── ...             #   如 nurse/{admission,bed,patient,order,vitals,docs,print,charge}
│   └── scripts/            # CLI 脚本 (schema/安装迁移 seed/演示数据 dictionary/导入导出 jobs/任务 diag/诊断)
└── public/                 # 前端 (Web 根目录)
    ├── index.php           # 唯一入口 (SPA 壳 + /api/ 路由)
    ├── assets.php          # 静态资源注册表 (JS/CSS 统一防缓存输出)
    ├── css/                # 样式 (core/login/admin/clinic/doctor/emr/nurse)
    ├── icons/              # 自绘 SVG 图标 ({分类}/{功能}.php)
    └── js/                 # JS + PHP 页面片段
        ├── core/           # 公共前端库 (根级应用外壳 + ui/ picker/ patient/ print/ pdf/ dict/)
        ├── login/          # 登录逻辑
        ├── shared/         # 各角色 sidebar (PHP)
        ├── emr_editor/     # 病历字处理编辑器 (外壳 + doc/ 文档引擎)
        └── {module}/       # 各工作站: 根级页面入口 {page}.php + {feature}/ 组件子目录
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
