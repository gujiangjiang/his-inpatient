// public/js/nurse/nursing_doc_templates.js - 常规护理文书内置 A4 表格式模板
// 5 套: 入院评估单 / 护理计划单 / 健康宣教单 / 转运(转科)记录单 / 通用护理文书
// 设计: 每套均为「一整张大表格」— 表头(医院名+文书名+患者信息行) → 分组表头行 → 填写/控件行
//   → 签名行(固定一行高, 护士/时间联动选择); h:null 的书写/数据行均分剩余高度填满整页。
// 控件: 单选/复选/数字/文本框/下拉(病区|护士)/日期时间, 编辑画布直接操作, 打印保留状态。
(function () {
  'use strict';
  const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
  const TPL = (window.NursingDocTemplates = {});

  /* ===== 模板清单 (key 与 nursing_docs.doc_type 对应; generic/other 归类「其他文书」) ===== */
  TPL.list = [
    { key: 'admission_assessment', name: '入院评估单', desc: '主诉病史 / 生命体征 / 身体评估 / 自理能力 / 评估意见' },
    { key: 'nursing_plan', name: '护理计划单', desc: '护理诊断 / 目标 / 措施 / 效果评价 计划表格' },
    { key: 'health_edu', name: '健康宣教单', desc: '入院 / 饮食 / 用药 / 检查 / 康复 / 出院 宣教记录' },
    { key: 'transfer_record', name: '转运(转科)记录单', desc: '转运前评估 / 交接内容 / 途中病情 / 双签名' },
    { key: 'generic', name: '通用护理文书', desc: '标题 + 正文 + 横线, 兼容任意内容书写' }
  ];
  TPL.name = function (key) {
    const hit = TPL.list.find(function (x) { return x.key === key; });
    return hit ? hit.name : '其他文书';
  };
  /* 文书归类 (批量打印/列表展示): 前四类独立, generic/other → 其他文书 */
  TPL.categoryOf = function (docType) {
    return ['admission_assessment', 'nursing_plan', 'health_edu', 'transfer_record'].indexOf(docType) >= 0 ? docType : 'other';
  };
  TPL.categoryName = function (docType) {
    return { admission_assessment: '入院评估', nursing_plan: '护理计划', health_edu: '健康宣教', transfer_record: '转运记录', other: '其他文书' }[TPL.categoryOf(docType)] || '其他文书';
  };

  /* ===== 剥离已嵌入的抬头 (旧版保存内容含 sheet-head+patient-bar, 现只存正文) ===== */
  TPL.stripHead = function (html) {
    const h = String(html || '');
    const pb = h.indexOf('<div class="patient-bar">');
    if (pb < 0) return h;
    const openEnd = h.indexOf('>', pb) + 1;
    const close = h.indexOf('</div>', openEnd);
    if (close < 0) return h;
    return h.slice(close + 6);
  };

  /* ===== 打印补充样式: 与编辑器画布逐像素一致 + 表单控件印刷适配 ===== */
  TPL.printCss = [
    '.sheet { padding: 10mm 13mm 14mm !important; }',
    '.sheet .print-brand-lockup { display: grid; justify-items: center; margin: 0 auto 2px; }',
    '.sheet .brand-name-lockup { margin: 0 auto; }',
    '.sheet .print-hospital { font-size: 18pt; line-height: 1.3; }',
    '.sheet .print-hospital2 { font-size: 10.5pt; }',
    '.sheet .sheet-docname { font-size: 15pt; line-height: 1.35; }',
    '.sheet .patient-bar { display: flex; flex-wrap: wrap; gap: 4px 16px; font-size: 10pt; padding: 4px 0; margin-bottom: 6px; border-bottom: 1px solid #c8ccd4; }',
    '.tpl-main { border-top: 1px solid #333; border-left: 1px solid #333; }',
    '.tpl-main .tpl-row { display: flex; overflow: hidden; border-bottom: 1px solid #333; }',
    '.tpl-main .tpl-cell, .tpl-main .tpl-label, .tpl-main .tpl-th { border-right: 1px solid #333; padding: 0 6px; display: flex; align-items: center; min-width: 0; }',
    '.tpl-main .tpl-cell { flex: 1 1 0; flex-wrap: wrap; align-content: center; row-gap: 2px; }',
    '.tpl-main .tpl-th { justify-content: center; }',
    '.tpl-ctl { border: 1px solid #555; background: #fff; font-size: 10pt; padding: 1px 4px; max-width: 100%; box-sizing: border-box; }',
    '.tpl-ctl-num { border: none; border-bottom: 1px solid #555; background: transparent; width: 60px; text-align: center; font-size: 10pt; }',
    '.tpl-ctl-radio, .tpl-ctl-chk { display: inline-flex; align-items: center; gap: 3px; margin-right: 6px; font-size: 10pt; white-space: nowrap; }',
    '.tpl-ctl input { accent-color: #333; vertical-align: middle; }',
    '.tpl-ctl-sel, .tpl-ctl-dt { border: 1px solid #555; background: #fff; font-size: 10pt; padding: 1px 4px; border-radius: 2px; box-sizing: border-box; max-width: 100%; width: 100%; height: 24px; font-family: inherit; color: #111; }',
    '.tpl-label { background: #f7f9fb; text-align: center; font-weight: 600; white-space: normal; line-height: 1.3; }',
    '.tpl-hdr { background: #eef2f6 !important; font-weight: 600; letter-spacing: 2px; }',
    '.tpl-label { flex-shrink: 0; }',
    '.tpl-th { flex-shrink: 0; }',
    '.tpl-seq { flex-shrink: 0; justify-content: center; }',
    '.tpl-seq { text-align: center; color: #909399; font-weight: 600; }'
  ].join('\n');

  /* ===== 患者信息行 (与打印页眉口径一致) ===== */
  function patientBar(p) {
    p = p || {};
    let age = p.age;
    if (p.age_snapshot) age = p.age_snapshot;
    else if (p.birth_date) age = Format.ageClinical(p.birth_date);
    const items = [
      ['姓名', p.patient_name || p.name || ''], ['性别', Format.gender(p.gender)],
      ['年龄', age || ''], ['科别', p.department_name || p.department || ''],
      ['病区', p.ward_name || p.ward || ''], ['床号', p.bed_no || p.bed || ''],
      ['住院号', p.admission_no || ''], ['入院日期', (p.admission_date || '').slice(0, 10)]
    ].filter(function (x) { return x[1] !== undefined && x[1] !== null && String(x[1]) !== ''; });
    return '<div class="patient-bar">' + items.map(function (x) {
      return '<span><b>' + esc(x[0]) + '：</b>' + esc(x[1]) + '</span>';
    }).join('') + '</div>';
  }

  /* ===== 表头 (医院名 + 文档名) ===== */
  function sheetHead(docName) {
    const brand = (typeof HospitalBranding !== 'undefined' && HospitalBranding.get)
      ? HospitalBranding.get() : {};
    return '<div class="sheet-head"><div class="sheet-brand">'
      + '<div class="print-brand-lockup">'
      + '<div class="brand-name-lockup"><div class="brand-name-line print-hospital">' + esc(brand.hospital_name || '') + '</div>'
      + (brand.hospital_name2 ? '<div class="brand-name-line print-hospital2">' + esc(brand.hospital_name2) + '</div>' : '')
      + '</div></div>'
      + '<div class="sheet-docname">' + esc(docName) + '</div></div></div>';
  }

  /* ===== 大表格行/列便捷生成 =====
     TD(t, style, cls): style 为内联样式(常用于 flex:0 0 X% 锁定列宽), cls 追加类名;
     注意 flex 子项必须用 flex-basis 而非 width 定宽 (.tpl-cell 默认 flex:1 1 0)。 */
  const TD = function (t, style, cls) { return '<div class="tpl-cell' + (cls ? ' ' + cls : '') + '"' + (style ? ' style="' + style + '"' : '') + '>' + (t == null ? '' : t) + '</div>'; };
  const TH = function (t, style) { return '<div class="tpl-th"' + (style ? ' style="' + style + '"' : '') + '>' + t + '</div>'; };
  const LBLR = function (t, w) { return '<div class="tpl-label" style="width:' + (w || '14%') + '"><b>' + t + '</b></div>'; };
  const CTRL = function (html) { return '<div class="tpl-cell">' + html + '</div>'; };
  const CTL = function (ph, w) { return '<input class="tpl-ctl" placeholder="' + (ph || '') + '"' + (w ? ' style="width:' + w + '"' : '') + '>'; };
  const NUM = function (w, attrs) { return '<input class="tpl-ctl-num"' + (w ? ' style="width:' + w + 'px"' : '') + (attrs ? ' ' + attrs : '') + '>'; };
  const RADIO = function (name, values) {
    return values.map(function (v) {
      return '<label class="tpl-ctl-radio"><input type="radio" name="' + name + '" value="' + v[0] + '">' + v[1] + '</label>';
    }).join('');
  };
  const CHK = function (name, values) {
    return values.map(function (v) {
      return '<label class="tpl-ctl-chk"><input type="checkbox" name="' + name + '" value="' + v[0] + '">' + v[1] + '</label>';
    }).join('');
  };
  const TA = function (rows) { return '<textarea class="tpl-ctl" rows="' + rows + '" style="width:100%"></textarea>'; };
  /* 联动控件: 病区/护士下拉 (选项由编辑器挂载后按 data-src 填充) 与日期/日期时间选择 */
  const SELW = function (ph) { return '<select class="tpl-ctl tpl-ctl-sel" data-src="wards"><option value="">' + (ph || '选择病区') + '</option></select>'; };
  const SELN = function (ph) { return '<select class="tpl-ctl tpl-ctl-sel" data-src="nurses"><option value="">' + (ph || '选择护士') + '</option></select>'; };
  const DT = function (ph) { return '<input class="tpl-ctl tpl-ctl-dt" data-dtpicker="datetime" placeholder="' + (ph || '年-月-日 时:分') + '" readonly>'; };
  const DATE = function (ph) { return '<input class="tpl-ctl tpl-ctl-dt" data-dtpicker="date" placeholder="' + (ph || '年-月-日') + '" readonly>'; };
  /* 签名行: 固定一行高 (44px), 标签 + 联动控件 (护士下拉 / 日期时间) */
  const SIGR = function (groups) {
    return { h: 44, cells: groups.map(function (g) { return LBLR(g[0]) + CTRL(g[1]); }) };
  };

  /* ===== 表体高预算 (与编辑器/打印 CSS 一致: A4 794×1123, 内边距 38/53, 表头+患者栏实测 139) ===== */
  const SHEET_H = 1123, PAD_TOP = 38, PAD_BOTTOM = 53;
  const HEAD_BLOCK_H = 139;
  const BODY_H = SHEET_H - PAD_TOP - PAD_BOTTOM - HEAD_BLOCK_H;   /* ≈ 893 */

  /* 显式行高布局: 纯 div 网格 (表格 tr/td 高度是 min-height 语义, 内容高过必撑破;
     div 行 height 固定 + overflow:hidden, 行高彻底锁定不被控件内容撑破)。
     h:null 的书写/数据行均分剩余高度 (末个 null 行取余数), 全部标记 tpl-fill 供 fitSheet 微调。 */
  function layout(rows) {
    const nullIdx = [];
    rows.forEach(function (r, i) { if (r.h == null) nullIdx.push(i); });
    const fixedUsed = rows.reduce(function (a, r) { return a + (r.h == null ? 0 : r.h); }, 0);
    const remain = BODY_H - fixedUsed;
    const share = nullIdx.length ? Math.max(30, Math.floor(remain / nullIdx.length)) : 0;
    const lastNull = nullIdx.length ? nullIdx[nullIdx.length - 1] : -1;
    let html = '';
    rows.forEach(function (r, i) {
      let h = r.h;
      let fill = false;
      if (h == null) {
        fill = true;
        h = (i === lastNull) ? Math.max(30, remain - share * (nullIdx.length - 1)) : share;
      }
      html += '<div class="tpl-row' + (fill ? ' tpl-fill' : '') + '" style="height:' + h + 'px">' + r.cells.join('') + '</div>';
    });
    return html;
  }

  function page(docName, patient, body) {
    return {
      title: docName,
      head: sheetHead(docName) + patientBar(patient),
      body: '<div class="tpl-main">' + body + '</div>'
    };
  }

  /* ===== 运行时自适应: 抬头(医院名/患者栏)实际高度随环境字体与换行浮动, 硬编码预算
     在部分环境会裁底或留缝。渲染后实测可用高并把差量均分到补齐行(tpl-fill), 使表体
     恰好填满画布: 编辑器传入 .sheet (表体容器为 .tpl-editor-body), 打印预览传入 iframe 内 .sheet。 ===== */
  TPL.fitSheet = function (sheetEl) {
    if (!sheetEl) return false;
    const main = sheetEl.querySelector('.tpl-main');
    if (!main) return false;
    const container = main.parentElement;
    if (!container) return false;
    let clipBottom = container.getBoundingClientRect().bottom;
    if (container === sheetEl) clipBottom -= parseFloat(getComputedStyle(sheetEl).paddingBottom) || 0;
    const delta = Math.round(clipBottom - main.getBoundingClientRect().bottom);
    if (Math.abs(delta) < 1) return true;
    const rows = Array.prototype.slice.call(main.querySelectorAll(':scope > .tpl-row'));
    if (!rows.length) return false;
    let fills = Array.prototype.slice.call(main.querySelectorAll(':scope > .tpl-fill'));
    if (!fills.length) fills = [rows[rows.length - 1]];
    /* 差量均分到补齐行 (末行取余), 单行最小 30px */
    const MIN = 30;
    const cur = fills.map(function (r) { return parseInt(r.style.height, 10) || 0; });
    const canAbsorb = fills.every(function (r, i) { return cur[i] + Math.floor(delta / fills.length) >= MIN; });
    if (canAbsorb) {
      let left = delta;
      fills.forEach(function (r, i) {
        const isLast = i === fills.length - 1;
        const d = isLast ? left : Math.round(delta / fills.length);
        left -= d;
        r.style.height = Math.max(MIN, cur[i] + d) + 'px';
      });
      return true;
    }
    /* 裁底超过补齐行可承受量: 全部行按比例缩小 */
    const total = rows.reduce(function (a, r) { return a + (parseInt(r.style.height, 10) || 0); }, 0);
    if (total <= 0) return false;
    const k = (total + delta) / total;
    rows.forEach(function (r) {
      r.style.height = Math.max(18, Math.round((parseInt(r.style.height, 10) || 0) * k)) + 'px';
    });
    return true;
  };

  /* ===== 对外: 组装 head + body ===== */
  TPL.build = function (key, patient) {
    const p = patient || {};
    if (key === 'admission_assessment') {
      /* 列网格: 标签列统一 14% — 单组行竖线 14%; 双组行 14/50/64; 三组行 14/47.3/80.7 (与签名行一致)。
         控件放不下的行 (多选项组) 行高 56, 允许组内换行, 不裁字; 评估意见行均分余量填满整页。 */
      const body = layout([
        { h: 28, cells: [TD('<b>一、主诉与病史</b>', '', 'tpl-hdr')] },
        { h: 72, cells: [LBLR('主诉'), CTRL(CTL('主诉', '100%'))] },
        { h: 84, cells: [LBLR('现病史'), CTRL(TA(3))] },
        { h: 84, cells: [LBLR('既往史'), CTRL(TA(3))] },
        { h: 28, cells: [TD('<b>二、生命体征</b>', '', 'tpl-hdr')] },
        { h: 56, cells: [LBLR('体温(℃)'), CTRL(NUM(60, 'data-f="temperature"') + ' 途径 ' + RADIO('tp', [['ax', '腋'], ['oral', '口'], ['an', '肛'], ['ear', '耳']])), LBLR('脉搏(次/分)'), CTRL(NUM(60, 'data-f="pulse"'))] },
        { h: 44, cells: [LBLR('呼吸(次/分)'), CTRL(NUM(60, 'data-f="respiratory_rate"')), LBLR('血压(mmHg)'), CTRL(NUM(50, 'data-f="bp_systolic"') + '/' + NUM(50, 'data-f="bp_diastolic"'))] },
        { h: 44, cells: [LBLR('血氧(%)'), CTRL(NUM(60, 'data-f="oxygen_saturation"')), LBLR('疼痛评分'), CTRL(NUM(60, 'data-f="pain_score"'))] },
        { h: 28, cells: [TD('<b>三、身体状况评估</b>', '', 'tpl-hdr')] },
        { h: 44, cells: [LBLR('意识'), CTRL(RADIO('con', [['1', '清醒'], ['2', '嗜睡'], ['3', '昏睡'], ['4', '昏迷']])), LBLR('瞳孔'), CTRL(CTL('等大等圆 / 对光反射', '100%'))] },
        { h: 56, cells: [LBLR('皮肤'), CTRL(CHK('skin', [['1', '完整'], ['2', '破损'], ['3', '压疮'], ['4', '皮疹'], ['5', '水肿']])), LBLR('过敏史'), CTRL(CTL('药物/食物过敏史', '100%'))] },
        { h: 56, cells: [LBLR('管路'), CTRL(CHK('pipe', [['1', '无'], ['2', '胃管'], ['3', '尿管'], ['4', '引流管'], ['5', '深静脉']])), LBLR('管路描述'), CTRL(CTL('', '100%'))] },
        { h: 28, cells: [TD('<b>四、生活自理</b>', '', 'tpl-hdr')] },
        { h: 56, cells: [LBLR('进食'), CTRL(RADIO('eat', [['1', '自理'], ['2', '协助'], ['3', '依赖']])), LBLR('排泄'), CTRL(RADIO('piss', [['1', '自理'], ['2', '协助'], ['3', '失禁']])), LBLR('活动'), CTRL(RADIO('move', [['1', '自如'], ['2', '协助'], ['3', '卧床']]))] },
        { h: 30, cells: [TD('<b>五、评估意见</b>', '', 'tpl-hdr')] },
        { h: null, cells: [LBLR('护理评估意见'), CTRL(TA(4))] },
        SIGR([['评估护士', SELN()], ['评估时间', DT()], ['复核人', SELN()]])
      ]);
      return page('入院评估单', p, body);
    }
    if (key === 'nursing_plan') {
      /* 列网格: 表头与数据行逐列同宽 (flex-basis), 诊断列 flex 撑满剩余 — 竖线上下一贯;
         12 行计划均分余量, 签名行固定一行高。 */
      const rows = [{ h: 30, cells: [TH('序号', 'flex:0 0 6%'), TH('开始日期', 'flex:0 0 12%'), TH('护理诊断/问题', 'flex:1 1 0;min-width:0'), TH('护理目标', 'flex:0 0 18%'), TH('护理措施', 'flex:0 0 24%'), TH('效果评价', 'flex:0 0 15%'), TH('停止日期', 'flex:0 0 10%')] }];
      for (let i = 1; i <= 12; i++) {
        rows.push({ h: null, cells: [TD(i, 'flex:0 0 6%', 'tpl-seq'), TD(CTL('', '90%'), 'flex:0 0 12%'), TD(TA(1)), TD(CTL('', '100%'), 'flex:0 0 18%'), TD(CTL('', '100%'), 'flex:0 0 24%'), TD(CTL('', '100%'), 'flex:0 0 15%'), TD(CTL('', '90%'), 'flex:0 0 10%')] });
      }
      rows.push(SIGR([['制定护士', SELN()], ['修订日期', DT()]]));
      return page('护理计划单', p, layout(rows));
    }
    if (key === 'health_edu') {
      const items = [['入院宣教', '病区环境/作息/探视制度/主管医护'], ['饮食指导', '治疗饮食种类与注意事项'],
        ['用药指导', '口服药用法/不良反应观察'], ['检查指导', '检查前准备/注意事项'],
        ['活动与康复指导', '活动强度/康复锻炼'], ['安全防护', '防跌倒/防坠床/管道安全'],
        ['心理疏导', '情绪支持/家属沟通'], ['出院指导', '复诊/用药/家庭护理']];
      /* 列网格: 表头与数据行逐列同宽; 宣教者(护士下拉)/宣教日期(日期选择)分列联动 */
      const rows = [{ h: 30, cells: [TH('序号', 'flex:0 0 5%'), TH('宣教项目', 'flex:0 0 12%'), TH('宣教要点', 'flex:1 1 0;min-width:0'), TH('方式', 'flex:0 0 16%'), TH('对象', 'flex:0 0 9%'), TH('评价', 'flex:0 0 14%'), TH('宣教者', 'flex:0 0 11%'), TH('宣教日期', 'flex:0 0 12%')] }];
      items.forEach(function (it, i) {
        rows.push({ h: 56, cells: [TD(i + 1, 'flex:0 0 5%', 'tpl-seq'), TD('<b>' + it[0] + '</b>', 'flex:0 0 12%'), TD(it[1]),
          TD(CHK('m' + i, [['y', '口头'], ['b', '书面'], ['s', '演示']]), 'flex:0 0 16%'), TD(RADIO('o' + i, [['p', '患者'], ['f', '家属']]), 'flex:0 0 9%'),
          TD(RADIO('r' + i, [['y', '掌握'], ['p', '部分'], ['n', '未掌握']]), 'flex:0 0 14%'), TD(SELN(), 'flex:0 0 11%'), TD(DATE(), 'flex:0 0 12%')] });
      });
      rows.push({ h: null, cells: [LBLR('补充宣教记录'), CTRL(TA(6))] });
      rows.push(SIGR([['责任护士', SELN()], ['护士长抽查', SELN()]]));
      return page('健康宣教单', p, layout(rows));
    }
    if (key === 'transfer_record') {
      /* 列网格: 标签列统一 14% (与入院评估单同规则); 病区走数据下拉, 时间走日期时间选择;
         交接两段书写区均分余量, 签名行固定一行高。 */
      const body = layout([
        { h: 28, cells: [TD('<b>一、转运信息</b>', '', 'tpl-hdr')] },
        { h: 44, cells: [LBLR('转出病区'), CTRL(SELW()), LBLR('转入病区'), CTRL(SELW()), LBLR('转出时间'), CTRL(DT())] },
        { h: 44, cells: [LBLR('转运方式'), CTRL(RADIO('tway', [['1', '轮椅'], ['2', '平车'], ['3', '步行']]) + ' 转运人员 ' + SELN())] },
        { h: 28, cells: [TD('<b>二、转运前评估</b>', '', 'tpl-hdr')] },
        { h: 44, cells: [LBLR('生命体征'), CTRL('体温 ' + NUM(50) + '℃ 脉搏 ' + NUM(50) + ' 呼吸 ' + NUM(50) + ' 血压 ' + NUM(50) + '/' + NUM(50) + ' 血氧 ' + NUM(50) + '%')] },
        { h: 44, cells: [LBLR('意识'), CTRL(RADIO('con', [['1', '清醒'], ['2', '嗜睡'], ['3', '昏睡'], ['4', '昏迷']])), LBLR('皮肤'), CTRL(RADIO('skin', [['1', '完整'], ['2', '破损'], ['3', '压疮']]))] },
        { h: 56, cells: [LBLR('管路'), CTRL(CHK('pipe', [['1', '无'], ['2', '胃管'], ['3', '尿管'], ['4', '引流管'], ['5', '深静脉']]) + ' 输液 ' + RADIO('inf', [['y', '有'], ['n', '无']])), LBLR('引流情况'), CTRL(CTL('', '100%'))] },
        { h: 28, cells: [TD('<b>三、转运交接</b>', '', 'tpl-hdr')] },
        { h: null, cells: [LBLR('携带物品/药品/资料'), CTRL(TA(3))] },
        { h: null, cells: [LBLR('途中病情变化及处理'), CTRL(TA(3))] },
        { h: 28, cells: [TD('<b>四、签名</b>', '', 'tpl-hdr')] },
        SIGR([['转出护士', SELN()], ['接收护士', SELN()], ['接收时间', DT()]])
      ]);
      return page('转运(转科)记录单', p, body);
    }
    /* 通用护理文书: 兼容任意输入的通用文书 (正文/续页均分余量, 签名固定一行) */
    const body = layout([
      { h: 28, cells: [TD('<b>文书记录</b>', '', 'tpl-hdr')] },
      { h: 44, cells: [LBLR('标题/主题'), CTRL(CTL('文书主题', '100%'))] },
      { h: null, cells: [LBLR('正文内容'), CTRL(TA(6))] },
      { h: null, cells: [LBLR('续页记录'), CTRL('')] },
      SIGR([['记录护士', SELN()], ['记录时间', DT()], ['护士长审核', SELN()]])
    ]);
    return page('通用护理文书', p, body);
  };
})();
