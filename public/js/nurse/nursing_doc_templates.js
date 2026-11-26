// public/js/nurse/nursing_doc_templates.js - 常规护理文书内置 A4 表格式模板
// 5 套: 入院评估单 / 护理计划单 / 健康宣教单 / 转运(转科)记录单 / 通用护理文书
// 设计: 每套均为「一整张大表格」— 表头(医院名+文书名+患者信息行) → 分组表头行 → 填写/控件行
//   → 签名行; 各段显式行高, 末行自动补齐余数, 总高恰等于表体可用高 (无长短不齐, 填满整页)。
// 控件: 单选/复选/数字/文本框, 编辑画布可直接操作, 打印保留勾选与数值。
(function () {
  'use strict';
  const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
  const TPL = (window.NursingDocTemplates = {});

  /* ===== 模板清单 (key 与 nursing_docs.doc_type 对应) ===== */
  TPL.list = [
    { key: 'admission_assessment', name: '入院评估单', desc: '主诉病史 / 生命体征 / 身体评估 / 自理能力 / 评估意见' },
    { key: 'nursing_plan', name: '护理计划单', desc: '护理诊断 / 目标 / 措施 / 效果评价 计划表格' },
    { key: 'health_edu', name: '健康宣教单', desc: '入院 / 饮食 / 用药 / 检查 / 康复 / 出院 宣教记录' },
    { key: 'transfer_record', name: '转运(转科)记录单', desc: '转运前评估 / 交接内容 / 途中病情 / 双签名' },
    { key: 'generic', name: '通用护理文书', desc: '标题 + 正文 + 横线, 兼容任意内容书写' }
  ];
  TPL.name = function (key) {
    const hit = TPL.list.find(function (x) { return x.key === key; });
    return hit ? hit.name : '护理文书';
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
  const NUM = function (w) { return '<input class="tpl-ctl-num"' + (w ? ' style="width:' + w + 'px"' : '') + '>'; };
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
  /* 签名格: 标签宽与模板主网格一致 (14%), 三组签名行的竖线与三组控件行对齐 */
  const SIG = function (t) { return LBLR(t) + '<div class="tpl-cell"></div>'; };

  /* ===== 表体高预算 (与编辑器/打印 CSS 一致: A4 794×1123, 内边距 38/53, 表头+患者栏实测 139) ===== */
  const SHEET_H = 1123, PAD_TOP = 38, PAD_BOTTOM = 53;
  const HEAD_BLOCK_H = 139;
  const BODY_H = SHEET_H - PAD_TOP - PAD_BOTTOM - HEAD_BLOCK_H;   /* ≈ 893 */

  /* 显式行高布局: 纯 div 网格 (表格 tr/td 高度是 min-height 语义, 内容高过必撑破;
     div 行 height 固定 + overflow:hidden, 行高彻底锁定不被控件内容撑破)。
     h:null 的补齐行标记 tpl-fill, 供 fitSheet 运行时微调。 */
  function layout(rows) {
    let used = 0;
    let html = '';
    rows.forEach(function (r, i) {
      let h = r.h;
      const fill = (h == null);
      if (fill) h = BODY_H - used;
      used += h;
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
     在部分环境会裁底或留缝。渲染后实测可用高并微调补齐行, 使表体恰好填满画布:
     编辑器传入 .sheet (表体容器为 .tpl-editor-body), 打印预览传入 iframe 内 .sheet。 ===== */
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
    const fill = main.querySelector(':scope > .tpl-fill') || rows[rows.length - 1];
    const cur = parseInt(fill.style.height, 10) || 0;
    const MIN = 30;
    if (delta > 0 || cur + delta >= MIN) {
      fill.style.height = Math.max(MIN, cur + delta) + 'px';
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
         控件放不下的行 (多选项组) 行高 56, 允许组内换行, 不裁字。 */
      const body = layout([
        { h: 28, cells: [TD('<b>一、主诉与病史</b>', '', 'tpl-hdr')] },
        { h: 72, cells: [LBLR('主诉'), CTRL(CTL('', '100%'))] },
        { h: 84, cells: [LBLR('现病史'), CTRL(TA(3))] },
        { h: 84, cells: [LBLR('既往史'), CTRL(TA(3))] },
        { h: 28, cells: [TD('<b>二、生命体征</b>', '', 'tpl-hdr')] },
        { h: 56, cells: [LBLR('体温(℃)'), CTRL(NUM(60) + ' 途径 ' + RADIO('tp', [['ax', '腋'], ['oral', '口'], ['an', '肛'], ['ear', '耳']])), LBLR('脉搏(次/分)'), CTRL(NUM(60))] },
        { h: 44, cells: [LBLR('呼吸(次/分)'), CTRL(NUM(60)), LBLR('血压(mmHg)'), CTRL(NUM(50) + '/' + NUM(50))] },
        { h: 44, cells: [LBLR('血氧(%)'), CTRL(NUM(60)), LBLR('疼痛评分'), CTRL(NUM(60))] },
        { h: 28, cells: [TD('<b>三、身体状况评估</b>', '', 'tpl-hdr')] },
        { h: 44, cells: [LBLR('意识'), CTRL(RADIO('con', [['1', '清醒'], ['2', '嗜睡'], ['3', '昏睡'], ['4', '昏迷']])), LBLR('瞳孔'), CTRL(CTL('等大等圆 / 对光反射', '100%'))] },
        { h: 56, cells: [LBLR('皮肤'), CTRL(CHK('skin', [['1', '完整'], ['2', '破损'], ['3', '压疮'], ['4', '皮疹'], ['5', '水肿']])), LBLR('过敏史'), CTRL(CTL('药物/食物过敏史', '100%'))] },
        { h: 56, cells: [LBLR('管路'), CTRL(CHK('pipe', [['1', '无'], ['2', '胃管'], ['3', '尿管'], ['4', '引流管'], ['5', '深静脉']])), LBLR('管路描述'), CTRL(CTL('', '100%'))] },
        { h: 28, cells: [TD('<b>四、生活自理</b>', '', 'tpl-hdr')] },
        { h: 56, cells: [LBLR('进食'), CTRL(RADIO('eat', [['1', '自理'], ['2', '协助'], ['3', '依赖']])), LBLR('排泄'), CTRL(RADIO('piss', [['1', '自理'], ['2', '协助'], ['3', '失禁']])), LBLR('活动'), CTRL(RADIO('move', [['1', '自如'], ['2', '协助'], ['3', '卧床']]))] },
        { h: 30, cells: [TD('<b>五、评估意见</b>', '', 'tpl-hdr')] },
        { h: 100, cells: [LBLR('护理评估意见'), CTRL(TA(4))] },
        { h: null, cells: [SIG('评估护士'), SIG('评估时间'), SIG('复核人')] }
      ]);
      return page('入院评估单', p, body);
    }
    if (key === 'nursing_plan') {
      /* 列网格: 表头与数据行逐列同宽 (flex-basis), 诊断列 flex 撑满剩余 — 竖线上下一贯 */
      const rows = [{ h: 30, cells: [TH('序号', 'flex:0 0 6%'), TH('开始日期', 'flex:0 0 12%'), TH('护理诊断/问题', 'flex:1 1 0;min-width:0'), TH('护理目标', 'flex:0 0 18%'), TH('护理措施', 'flex:0 0 24%'), TH('效果评价', 'flex:0 0 15%'), TH('停止日期', 'flex:0 0 10%')] }];
      for (let i = 1; i <= 12; i++) {
        rows.push({ h: 64, cells: [TD(i, 'flex:0 0 6%', 'tpl-seq'), TD(CTL('', '90%'), 'flex:0 0 12%'), TD(TA(1)), TD(CTL('', '100%'), 'flex:0 0 18%'), TD(CTL('', '100%'), 'flex:0 0 24%'), TD(CTL('', '100%'), 'flex:0 0 15%'), TD(CTL('', '90%'), 'flex:0 0 10%')] });
      }
      rows.push({ h: null, cells: [LBLR('制定护士', '12%'), TD(''), LBLR('修订日期', '12%'), TD('')] });
      return page('护理计划单', p, layout(rows));
    }
    if (key === 'health_edu') {
      const items = [['入院宣教', '病区环境/作息/探视制度/主管医护'], ['饮食指导', '治疗饮食种类与注意事项'],
        ['用药指导', '口服药用法/不良反应观察'], ['检查指导', '检查前准备/注意事项'],
        ['活动与康复指导', '活动强度/康复锻炼'], ['安全防护', '防跌倒/防坠床/管道安全'],
        ['心理疏导', '情绪支持/家属沟通'], ['出院指导', '复诊/用药/家庭护理']];
      /* 列网格: 表头与数据行逐列同宽; 方式/评价列加宽, 控件组超宽时换行 (行高 56 容纳两行) */
      const rows = [{ h: 30, cells: [TH('序号', 'flex:0 0 5%'), TH('宣教项目', 'flex:0 0 12%'), TH('宣教要点', 'flex:1 1 0;min-width:0'), TH('方式', 'flex:0 0 18%'), TH('对象', 'flex:0 0 10%'), TH('评价', 'flex:0 0 16%'), TH('宣教者/日期', 'flex:0 0 14%')] }];
      items.forEach(function (it, i) {
        rows.push({ h: 56, cells: [TD(i + 1, 'flex:0 0 5%', 'tpl-seq'), TD('<b>' + it[0] + '</b>', 'flex:0 0 12%'), TD(it[1]),
          TD(CHK('m' + i, [['y', '口头'], ['b', '书面'], ['s', '演示']]), 'flex:0 0 18%'), TD(RADIO('o' + i, [['p', '患者'], ['f', '家属']]), 'flex:0 0 10%'),
          TD(RADIO('r' + i, [['y', '掌握'], ['p', '部分'], ['n', '未掌握']]), 'flex:0 0 16%'), TD(CTL('宣教者/日期', '90%'), 'flex:0 0 14%')] });
      });
      rows.push({ h: 170, cells: [LBLR('补充宣教记录', '12%'), TD(TA(6))] });
      rows.push({ h: null, cells: [LBLR('责任护士', '12%'), TD(''), LBLR('护士长抽查', '12%'), TD('')] });
      return page('健康宣教单', p, layout(rows));
    }
    if (key === 'transfer_record') {
      /* 列网格: 标签列统一 14% (与入院评估单同规则); 管路行控件组多, 行高 56 允许换行 */
      const body = layout([
        { h: 28, cells: [TD('<b>一、转运信息</b>', '', 'tpl-hdr')] },
        { h: 44, cells: [LBLR('转出病区'), CTRL(CTL('', '90px')), LBLR('转入病区'), CTRL(CTL('', '90px')), LBLR('转出时间'), CTRL(CTL('', '110px'))] },
        { h: 44, cells: [LBLR('转运方式'), CTRL(RADIO('tway', [['1', '轮椅'], ['2', '平车'], ['3', '步行']]) + ' 转运人员 ' + CTL('', '90px'))] },
        { h: 28, cells: [TD('<b>二、转运前评估</b>', '', 'tpl-hdr')] },
        { h: 44, cells: [LBLR('生命体征'), CTRL('体温 ' + NUM(50) + '℃ 脉搏 ' + NUM(50) + ' 呼吸 ' + NUM(50) + ' 血压 ' + NUM(50) + '/' + NUM(50) + ' 血氧 ' + NUM(50) + '%')] },
        { h: 44, cells: [LBLR('意识'), CTRL(RADIO('con', [['1', '清醒'], ['2', '嗜睡'], ['3', '昏睡'], ['4', '昏迷']])), LBLR('皮肤'), CTRL(RADIO('skin', [['1', '完整'], ['2', '破损'], ['3', '压疮']]))] },
        { h: 56, cells: [LBLR('管路'), CTRL(CHK('pipe', [['1', '无'], ['2', '胃管'], ['3', '尿管'], ['4', '引流管'], ['5', '深静脉']]) + ' 输液 ' + RADIO('inf', [['y', '有'], ['n', '无']])), LBLR('引流情况'), CTRL(CTL('', '100%'))] },
        { h: 28, cells: [TD('<b>三、转运交接</b>', '', 'tpl-hdr')] },
        { h: 72, cells: [LBLR('携带物品/药品/资料'), CTRL(TA(3))] },
        { h: 72, cells: [LBLR('途中病情变化及处理'), CTRL(TA(3))] },
        { h: 28, cells: [TD('<b>四、签名</b>', '', 'tpl-hdr')] },
        { h: null, cells: [SIG('转出护士'), SIG('接收护士'), SIG('接收时间')] }
      ]);
      return page('转运(转科)记录单', p, body);
    }
    /* 通用护理文书: 兼容任意输入的通用文书 (补齐行放末行, 总高恰为整页) */
    const body = layout([
      { h: 28, cells: [TD('<b>文书记录</b>', '', 'tpl-hdr')] },
      { h: 44, cells: [LBLR('标题/主题', '14%'), CTRL(CTL('文书主题', '100%'))] },
      { h: 108, cells: [LBLR('正文内容', '14%'), CTRL(TA(4))] },
      { h: 150, cells: [LBLR('续页记录', '14%'), CTRL('')] },
      { h: null, cells: [SIG('记录护士'), SIG('记录时间'), SIG('护士长审核')] }
    ]);
    return page('通用护理文书', p, body);
  };
})();
