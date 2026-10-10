# 变更日志

所有重要变更记录在本文件中。

格式参照 [语义化版本](https://semver.org/)。

## [0.13.35] - 2026-10-10

### 变更
- 工具栏「批量删除」改为「快速操作」下拉: 批量停止 (仅长期且可停止状态) / 批量复制 (非草稿) / 批量删除 (仅草稿), 结果 toast 汇总

## [0.13.34] - 2026-10-10

### 修复
- 仅长期医嘱可停止: 单个停止/状态机停止均校验 (临时医嘱提示作废或删除), 操作菜单对临时医嘱不再显示停止
- 停止时间不得早于开具时间: 单个停止与长期全停 (SQL 条件过滤) 双重保障
- 长期全停 SQL 清理重复校验并补充开具时间条件

## [0.13.33] - 2026-10-10

### 修复
- 详情框点击开始时间弹出日期选择框时悬浮框被误关闭 (外部点击判定把日期弹层当作外部): 日期弹层 `.dtp` 视为悬浮框交互一部分, Esc 在有日期弹层时优先关闭日期弹层

## [0.13.32] - 2026-10-10

### 变更
- 医嘱详情框对非草稿 (待核对/已核对/执行中/停止/作废) 开放: 内容全只读, 隐藏保存按钮; 药品说明书/计价项目/更多 (停止/复制/作废等) 照常可用; 后端 orders/update 本就仅限草稿, 双重拦截
- 详情框触发点改为**仅医嘱内容文字** (hover 显示下划线), 剂量/空白区域不再触发

## [0.13.31] - 2026-10-10

### 修复
- 复制医嘱不再写入「（复制自 #xx）」备注, 副本医嘱说明为空

## [0.13.30] - 2026-10-10

### 修复
- 医嘱工作站/患者卡片墙被 main-content 的 20px padding 包裹导致四边白边: 全屏页自动去除留白 (`:has` 选择器), 其余页面布局不受影响

## [0.13.29] - 2026-10-10

### 新增 (门诊一体化预留)
- 迁移 016: 对接端点表 `integration_endpoints` (FHIR / HL7 / HIS / DICOM, 业务类型: 检验/检查/处方/处置/患者, 鉴权 none/basic/token/oauth2)
- 管理端「对接管理」页 (侧边栏入口): 端点增删改查/启停, 鉴权配置 JSON 校验
- 接口: `admin/integration-list|save|delete`; 新增 `integration_helper` (`integration_endpoints_for` / `integration_dispatch` 适配器预留)

## [0.13.28] - 2026-10-10

### 变更
- 非药品医嘱目录 (seed) 调整原则: 默认不设途径 (护理/测量/饮食/宣教等), 仅特殊项保留 (弹力袜-外用), 新增 蛋白粉 (途径可手动选择)

## [0.13.27] - 2026-10-10

### 修复
- 患者列表 (卡片墙) 不再显示患者信息栏与「切换患者」按钮: 信息栏仅在进入某一患者页面后显示, 返回卡片墙自动清空隐藏

## [0.13.26] - 2026-10-10

### 修复
- 患者列表卡片仅显示**已入科**患者 (已指派主管医师); 待入科患者只在「添加患者」弹窗出现
- 未入科患者禁止开医嘱: `order_save` / `set_apply` 后端拦截 (前后端双重防护)
- 开嘱时间不得早于入科时间: 新建 (order_save) 与修改开始时间 (order_update) 均校验
- 新增 `patient_admitted_at` / `is_patient_admitted` helper

## [0.13.25] - 2026-10-09

### 修复
- 患者卡片护理等级改为取**最新开具的有效护理医嘱** (未停止/未作废, 按开单顺序取最新): 如忘停一级护理又开二级护理, 卡片显示二级; 患者信息栏同步
- 新增 `patient_helper::apply_latest_nursing_level`, 应用于 患者列表/主管患者/科室患者/患者详情

## [0.13.24] - 2026-10-09

### 新增
- 医嘱说明以「（说明）」小一号斜体展示在医嘱内容后 (如 术前30分钟 / 术中 / 书中追加)

## [0.13.23] - 2026-10-09

### 新增
- **私有日期/时间选择组件** `DateTimePicker` (不依赖原生控件): 三种类型 `data-dtpicker="date|time|datetime"`, 含年月选择/日历/时分选择/今天/现在/清空/确定, 自动视口定位
- 接入: 医嘱筛选日期范围 (纯日期), 悬浮框开始时间 (日期+时间); 全站可复用 (`DateTimePicker.initInputs(容器)`)
- 无头 Chrome E2E: 筛选日期选择与详情框日期时间选择并保存入库 均通过

## [0.13.22] - 2026-10-09

### 变更
- 悬浮详情框紧凑化: 宽度 520→470, 行距/内边距收紧, 持续时间(56px)与执行时间同行展示, 时长单位 62px

## [0.13.21] - 2026-10-09

### 变更
- 缩短 途径(150→108) / 频次(64→46) / 执行时间(110→84) 列宽, 释放空间给医嘱内容列

## [0.13.20] - 2026-10-09

### 新增
- 计价改为下拉选择: 正常计价 / 不计价 / 不摆药 (不摆药仅药品可见); 附加标签保留 径外/自费/预防
- 「不摆药」医嘱不进入药房摆药队列 (待摆药列表自动过滤), 护士可直接执行, 核对环节不受影响
- 药品/非药品区分: 非药品隐藏「不摆药」选项与「药品说明书」按钮

## [0.13.19] - 2026-10-09

### 修复
- 悬浮框「更多」菜单优化: 菜单定位限制在视口内不再溢出浏览器; 菜单与悬浮框共存亡 (点击菜单不关闭悬浮框, 悬浮框关闭时菜单同步关闭, 选择菜单操作后两者一起关闭)

## [0.13.18] - 2026-10-09

### 修复
- 临时医嘱无频次/执行时间 (前端禁用 + 后端强制):
  - 保存/套用/修改为临时医嘱时自动清空频次与执行时间; 对临时医嘱写入频次字段直接忽略
  - 列表频次/执行时间单元格置灰不可点击; 详情框执行时间禁用; 快速开嘱/编辑器选临时时自动剔除频次

## [0.13.17] - 2026-10-09

### 修复
- 医嘱顺序固定为开单顺序 (`orders/list` 改为 `ORDER BY id ASC`), 修改开始时间不再影响排列; 护士执行端仍可按时间排序
- 除新开医嘱外不再自动滚动定位: 保存/切换长临时/行内修改/详情框保存均保持原滚动位置 (refresh 保留 scrollTop), 仅新开医嘱定位并闪烁

## [0.13.16] - 2026-10-09

### 新增
- **医嘱悬浮详情框**: 点击草稿医嘱行在鼠标旁弹出悬浮框 (替代原行下展开抽屉), 含 开始时间 / 医嘱内容(只读) / 医嘱说明 / 持续时间(数值+单位) / 执行时间 / 计价(正常计价 + 径外/自费/不计价/预防) ; 操作按钮 药品说明书 / 计价项目 / 保存 / 更多 (更多=右侧「⋯」操作菜单)
- `orders/update` 扩展支持 start_date / notes / duration 字段实时保存
- 悬浮框打开时整组医嘱行高亮; 点击外部/Esc/保存后关闭

## [0.13.15] - 2026-10-09

### 新增
- 医嘱表新增「途径 / 频次 / 执行时间」独立列 (成组医嘱与组同高合并); 草稿可直接点击单元格修改途径/频次/执行时间, 变更即实时保存 (select 选择即存, 输入 Enter/失焦保存, Esc 取消)
- 执行时间自动生成: 频次 qd→07, bid→07-16, tid→07-11-16, qid→07-11-16-21, qn→20 (也可手动输入); 快速开嘱与编辑器选择频次时自动带出

## [0.13.14] - 2026-10-09

### 新增
- 医嘱左侧「长/临」徽章可点击快速切换长期/临时 (草稿医嘱, 组内子医嘱同步, 实时写库 + toast 提示)

## [0.13.13] - 2026-10-09

### 变更
- **前端静态资源统一管理**: 新增 `public/assets.php` (`Asset::$css` / `Asset::$js` 注册表 + `Asset::css()` / `Asset::js()` 输出, 自动附带 APP_VERSION 防缓存); `index.php` 改为统一调用; AGENT.md 约定新增/移除资源只改 assets.php, 禁止页面散写标签
- 同时约定页面片段禁止自定义 `navigateTo`, 统一使用全局 hash 路由

## [0.13.12] - 2026-10-09

### 修复
- 医嘱「⋯」操作菜单第二次点击无反应: 菜单项点击 `stopPropagation` 导致全局关闭监听从未移除, 残留监听会把下次打开的菜单立即关闭; 改为在 `closeMenu` 中统一移除关闭监听

## [0.13.11] - 2026-10-09

### 变更
- 医嘱操作提示语明确实时写库: 开嘱「已实时保存草稿: xxx」、行内修改「草稿已实时保存」; 删除/停止/作废/提交/复制/套用组套等操作均已有 toast 且实时落库 (草稿同样即时写入)

## [0.13.10] - 2026-10-09

### 变更
- 静态资源防缓存: 全部 CSS/JS 引入追加 `?v=APP_VERSION`, `AjaxLoader.loadPage` 页面片段同样带版本号 + `cache: no-store`, API GET 请求禁用缓存; 避免浏览器读到旧脚本导致「修复不生效」

## [0.13.9] - 2026-10-09

### 修复
- **根治「进入医嘱页侧边栏不显示 / 刷新后医嘱看似丢失」**: 23 个页面片段内重复定义的 `navigateTo` 覆盖了 app.js 的 hash 路由版本, 点击患者后 URL 不变、布局不刷新、F5 会回到卡片墙; 已全部移除, 统一使用全局 hash 路由
- 端到端回归验证 (无头 Chrome): 卡片墙点击患者 → URL 变为 `#orders/order_station` + 侧边栏显示; 新增草稿后整页刷新医嘱仍在 (草稿实时写库)

## [0.13.8] - 2026-10-09

### 修复
- 患者卡片墙左侧栏重复显示: 浏览页 (卡片墙/床位) 无论是否已选患者都隐藏侧边栏
- 开嘱后自动定位: 新开药品/非药品/组套套用/编辑保存后, 列表自动滚动到该医嘱并整组闪烁高亮

### 变更
- 医嘱文字状态配色: 已核对黑色 / 待核对黄色 / 草稿蓝色 / 作废红色(删除线) / 停止灰色; 移除状态小徽章
- 状态过滤重定义: 全部(含作废) / 医嘱本(去作废) / 作废 / 停止(显示停止时间) / 待完成(草稿)
- 患者信息条移除「患者列表」按钮, 点击左侧床徽 (Ⅰ|1) 返回患者卡片墙

## [0.13.7] - 2026-10-09

### 新增
- **切换患者悬浮窗**: 医嘱页点「切换患者」在按钮下方弹出患者列表 (床徽/姓名/性别年龄/病区, 当前患者标记), 点击直接切换到该患者医嘱页; 底部入口跳转患者卡片墙。修复原行为「清空上下文后停留在医嘱页且侧边栏消失」

### 变更
- **非药品不再成组**: 多条非药品逐条保存为独立医嘱 (`order_save` / `set_apply` 仅药品成组); 迁移 015 自动拆分历史非药品成组数据 (如 一级护理+测血压)
- 成组医嘱开始时间列与组同高合并居中 (rowspan), 废除子行时间占位
- 成组括号由竖线改为 `}` 形曲线括号 (SVG 自适应拉伸), 与参考系统一致更直观
- 行内编辑抽屉移至整组之后渲染并高亮编辑行, 修复点击草稿子医嘱导致表格错乱无法操作的问题

## [0.13.6] - 2026-10-09

### 修复
- **跨科室越权彻底封堵**: 取消「指派管辖」对患者访问的放行, 医生严格限授权科室范围 —
  - `patients/list` 全部科室 = 授权科室并集, 不再混入指派的外科患者
  - `my_patients` / `incoming_queue` 同步按授权科室过滤 (原无授权时会返回全部, 已封堵)
  - `patient_get` / `can_access_patient` 不再因指派跨科放行, 跨科查看/开嘱/改嘱一律 403
  - 医生指派医师仅限授权科室患者
- 工作站未选患者时左侧边栏默认隐藏 (选择患者后才出现), 卡片墙不再显示侧边栏
- 成组医嘱子医嘱错位到「开始时间」列: 子行补时间列占位, 医嘱内容回到「医嘱内容」列

## [0.13.5] - 2026-10-09

### 变更
- **SVG 图标改为 PHP 绘制并集中管理**: 新增 `public/icons/` 图标库 — `icon.php` 渲染器 (`icon('分类', '名称', 尺寸, class, attrs)`) + 多级分类目录 (`common/arrows.php` 箭头、`common/actions.php` 操作、`common/status.php` 状态、`medical/objects.php` 医疗对象) + `js.php` JS 桥接 (`svgIcon('分类/名称', 尺寸)`); 删除 JS 版 `icons.js`, 全站内联 SVG 全部清理; AGENT.md 同步图标规范与目录约定

### 修复
- 患者卡片墙科室下拉显示全部科室: 医生仅显示本人科室 + `doctor_department_permissions` 授权科室, admin 显示全部

### 安全
- 数据权限后端拦截: 新增 `department_helper` (`allowed_department_ids` / `can_access_department` / `can_access_patient`); `patients/list|get|save|by-department|discharge`, `orders/save|list|update|submit|stop|copy|invalidate|verify|batch-delete` 全部校验授权科室或指派管辖, 越权请求返回 403

## [0.13.4] - 2026-10-09

### 修复
- 患者列表下拉「科室患者+」改为在该菜单项下方就地展开科室列表 (原在整个下拉底部展开)
- 选择科室后卡片墙不显示患者 (工作站无患者上下文时被路由守卫拦回占位页): 患者卡片墙/床位页免上下文进入; 未选患者访问其他页面自动跳回卡片墙并提示; 「切换患者」直接返回卡片墙
- doctor 登录后默认进入患者卡片墙 (原停留在占位页)

### 变更
- 全站移除 emoji/字符图标 (🏥 🛠 ⚠ ✕ ⋯ ▾ ▸): 新增自绘 SVG 图标库 `public/js/core/icons.js` (医院/工具/警告/关闭/更多/方向箭头/患者), 登录 Logo、占位图、菜单箭头、行内按钮全部替换为 SVG
- 图标规范写入 AGENT.md: 禁止 emoji, 名称类图标统一入 Icons 库, 静态片段用内联 SVG

## [0.13.3] - 2026-10-09

### 新增
- **悬浮快速开嘱**: 点「药品 F9 / 非药品 F8」在鼠标旁弹出搜索悬浮窗 (药品/非药品统一检索, 支持名称/拼音/编码), 点击或 Enter 直接加入医嘱列表, 悬浮窗保持打开可连续开嘱; 非药品无匹配时回车可加入自定义内容
- **行内修改**: 草稿医嘱点击行即可在列表内展开编辑抽屉, 直接改内容/剂量/单位/用法/滴速/频次/执行时间/标签/长期临时, Enter 保存 Esc 取消
- 迁移 014: 非药品医嘱目录表 `order_catalog` (内置 24 条常用非药品: 各级护理/饮食/测量/监护/宣教等)
- 接口: orders/quick-search (药品+非药品统一检索), orders/update (草稿行内修改, 校验医生管辖)

### 变更
- 开嘱入口由模态编辑器改为悬浮搜索; 模态编辑器保留为医嘱行「⋯ → 整组编辑」, 用于整组调整

## [0.13.2] - 2026-10-09

### 修复
- 登录页居中失效 (showLogin 将 display 设为 block 覆盖了 flex): 改回 flex, 登录框不再偏居左上角
- 开药品医嘱报错 `Cannot set properties of null (setting 'innerHTML')`: Dom.modal 支持弹窗叠加 (不再清除已有弹窗, 层级递增), 药品检索可覆盖在开嘱编辑器之上; renderItems 增加防御
- 刷新按钮无反馈: 手动刷新后提示刷新时间与医嘱组数

### 变更
- 登录页整体重设计: 青绿渐变背景 + 装饰圆 + 品牌 Logo/副标题 + 输入聚焦态 + 居中卡片动画, 医院名称同步到登录标题
- 患者列表下拉只保留分类/科室 (添加患者/主管患者/科室患者+/大小卡切换/出院患者), 不再内联患者卡片; 点击分类跳转患者卡片墙并按 主管/科室/出院 筛选, 卡片墙显示筛选标题与「返回全部」
- navigateTo 同步 hash 路由 (#module/page), 支持浏览器前进/后退; 大小卡状态全局共享

## [0.13.1] - 2026-10-09

### 变更
- 本地开发端口固定为 9095 (`npm run dev` 同步调整), 写入 AGENT.md 约定
- 新增 `npm run server` (`tools/server.sh`): 检测 9095 服务器, 不存在则自动启动; AI 每次代码修改/优化后必须执行
- README 更新本地开发说明与端口

## [0.13.0] - 2026-10-09

### 新增 (医嘱工作站重构)
- **数据模型**: 迁移 012 患者扩展 (护理等级/费别/过敏史/危重/VTE 风险/累计费用); 迁移 013 医嘱结构化 (长期临时/医嘱流水号/药品关联/剂量单位/用法/滴速/频次时间/组内排序/标签/停止时间) + 药品目录扩展 (剂型/厂家/药房/基药等级/拼音码/默认用法) + 医嘱组套表 (order_sets / order_set_items)
- **医嘱工作站** (`orders/order_station`): 工具条 (药品 F9 / 非药品 F8 / 提交 F2 / 长期全停 F4 / 刷新 F5 / 路径组套 / 辅助决策), 长期临时 + 开始日期 + 状态 (医嘱本/作废/停止/待完成) 过滤; 表格按 长/临、开始时间、内容、剂量、用法频次、医生 展示; 成组医嘱以左侧圆括号连接并共享组用法/滴速; 径外/自费/不计价/预防 标签; 草稿/待核对/停止/作废 状态徽章
- **开嘱编辑器**: 药品模糊检索弹窗 (名称/拼音码/编码, ↑↓ Enter Esc), 成组药品 (溶媒 + 主药) 共享用法/滴速, 非药品多条录入, 剂量/单位/频次/执行时间/标签, 存草稿或保存并提交, 支持修改草稿
- **医嘱操作**: 提交 / 停止 / 长期全停 / 作废 / 复制 / 删除 / 批量删除, 新增 `stopped` 停止状态与状态机
- **医嘱组套 (路径)**: 组套列表/套用/另存, 演示内置「骨科术后抗感染组套 / VTE 预防组套 / 一级护理常规」
- **患者主页面卡片墙**: 护理等级配色床徽 (特/Ⅰ/Ⅱ/Ⅲ), 危重红框, 过敏角标, 入院天数, 费别, ID/主治, VTE Caprini/出血标签, 费用, 大小卡切换, 底部统计图例
- **患者切换器**: 改为下拉菜单 (添加患者 / 主管患者 / 科室患者+ / 大小卡切换 / 出院患者), 卡片显示床徽/主治/费别
- **患者信息条**: 床徽 + 姓名 + 性别/年龄/住院号/费别/病区 + 入院天数 + 红色费用 + 诊断 + 患者列表入口
- **接口**: pharmacy/drug-search, orders/stop, orders/set-list|set-save|set-apply; orders/save 支持 items 成组结构化; orders/list 支持多状态/长期临时/日期过滤并返回开嘱医生
- **演示数据**: seed_demo 药品目录扩充至 13 种 (含基药等级/厂家/库存) 并回填患者卡片字段; 新增 seed_demo_orders 医嘱/组套种子 (长期/临时/成组/多状态)

### 变更
- 医生工作站默认医嘱入口由 `orders/order_list` 切换至 `orders/order_station`, 选择患者后直接进入医嘱工作站
- patients/list 附带主治/上级医师并支持状态与科室过滤, 排序改为病区 + 床号
- patients/save 支持护理等级/费别/过敏史/VTE 字段
- 顶部主题调整为青色, 版本号 0.13.0 三处同步

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
