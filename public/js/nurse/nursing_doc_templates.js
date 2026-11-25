// public/js/nurse/nursing_doc_templates.js - 常规护理文书内置 A4 表格式模板
// 5 套: 入院评估单 / 护理计划单 / 健康宣教单 / 转运(转科)记录单 / 通用护理文书
// 每套模板 = head(标题+患者信息行) + body(表格正文), 复用 PrintDocument 的 sheet-head/patient-bar/sheet-table 样式;
//   所见即所得编辑器画布与 A4 打印共用同一份 HTML, 表格行数按 A4 内容区设计填满一页。
(function () {
  'use strict';
  const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
  const TPL = (window.NursingDocTemplates = {});

  /* ===== 模板清单 (key 与 nursing_docs.doc_type 对应) ===== */
  TPL.list = [
    { key: 'admission_assessment', name: '入院评估单', desc: '主诉病史 / 生命体征 / 身体评估 / 心理宣教 / 评估意见' },
    { key: 'nursing_plan', name: '护理计划单', desc: '护理诊断 / 目标 / 措施 / 效果评价 计划表格' },
    { key: 'health_edu', name: '健康宣教单', desc: '入院 / 饮食 / 用药 / 检查 / 康复 / 出院 宣教记录' },
    { key: 'transfer_record', name: '转运(转科)记录单', desc: '转运前评估 / 交接内容 / 途中病情 / 双签名' },
    { key: 'generic', name: '通用护理文书', desc: '空白横线文书, 兼容任意内容书写' }
  ];
  TPL.name = function (key) {
    const hit = TPL.list.find(x => x.key === key);
    return hit ? hit.name : '护理文书';
  };

  /* 剥离已嵌入的抬头 (旧版保存内容包含 sheet-head+patient-bar, 现只存正文) */
  TPL.stripHead = function (html) {
    const h = String(html || '');
    const pb = h.indexOf('<div class="patient-bar">');
    if (pb < 0) return h;
    const openEnd = h.indexOf('>', pb) + 1;
    const close = h.indexOf('</div>', openEnd);
    if (close < 0) return h;
    return h.slice(close + 6);
  };

  /* 打印补充样式: 抬头居中 + 表单控件印刷适配 */
  TPL.printCss = [
    '.sheet .print-brand-lockup { display: grid; justify-items: center; margin: 0 auto 8px; }',
    '.sheet .brand-name-lockup { margin: 0 auto; }',
    '.tpl-ctl { border: 1px solid #555; background: #fff; font-size: 10pt; padding: 1px 4px; max-width: 100%; }',
    '.tpl-ctl-num { border: none; border-bottom: 1px solid #555; background: transparent; width: 70px; text-align: center; font-size: 10pt; }',
    '.tpl-ctl-radio, .tpl-ctl-chk { display: inline-flex; align-items: center; gap: 4px; margin-right: 10px; font-size: 10pt; }',
    '.tpl-ctl input { accent-color: #333; vertical-align: middle; }'
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
    ].filter(x => x[1] !== undefined && x[1] !== null && String(x[1]) !== '');
    return '<div class="patient-bar">' + items.map(x =>
      '<span><b>' + esc(x[0]) + '：</b>' + esc(x[1]) + '</span>').join('') + '</div>';
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

  /* ===== 行/列便捷生成 ===== */
  const td = (t, o) => '<td' + (o || '') + '>' + (t == null ? '' : t) + '</td>';
  const row = (cells, cls) => '<tr' + (cls ? ' class="' + cls + '"' : '') + '>' + cells.join('') + '</tr>';
  const labelTd = (t, w) => '<td style="width:' + (w || '12%') + '" class="tpl-label"><b>' + t + '</b></td>';
  const secTitle = (t) => row([td('<b>' + t + '</b>', ' colspan="2" class="tpl-sec"')]);
  const blankRow = (label) => row([labelTd(label), td('')]);
  const multiBlank = function (label, rows, h) {
    const cells = [labelTd(label)];
    for (let i = 0; i < rows; i++) cells.push(td('', ' style="height:' + (h || 52) + 'px"'));
    return row(cells);
  };

  /* ===== 行/列便捷生成 (模板专用) ===== */
  const TH = (t, w) => '<th style="width:' + (w || '') + '">' + t + '</th>';
  const TD = (t, o) => '<td' + (o || '') + '>' + (t == null ? '' : t) + '</td>';
  const ROW = (cells, cls) => '<tr' + (cls ? ' class="' + cls + '"' : '') + '>' + cells.join('') + '</tr>';
  const SEC = (t) => ROW([TD('<b>' + t + '</b>', ' colspan="2" class="tpl-sec"')]);
  const LBL = (t, w) => '<td style="width:' + (w || '13%') + '" class="tpl-label"><b>' + t + '</b></td>';
  const INP = (ph, w) => '<input class="tpl-ctl" placeholder="' + (ph || '') + '"' + (w ? ' style="width:' + w + '"' : '') + '>';
  const NUM = (w) => '<input class="tpl-ctl-num" inputmode="decimal"' + (w ? ' style="width:' + w + 'px"' : '') + '>';
  /* 单选/多选: values 为 [[值, 标签]...] */
  const RADIO = (name, values) => values.map(v2 => '<label class="tpl-ctl-radio"><input type="radio" name="' + name + '" value="' + v2[0] + '">' + v2[1] + '</label>').join('');
  const CHK = (name, values) => values.map(v2 => '<label class="tpl-ctl-chk"><input type="checkbox" name="' + name + '" value="' + v2[0] + '">' + v2[1] + '</label>').join('');
  const LINES = (n) => { let h = ''; for (let i = 0; i < n; i++) h += '<div style="height:28px;border-bottom:1px solid #d9dce1"></div>'; return h; };
  /* 卡片式段落 (去冗余替代整页大空白格): 标题 + 控件行 */
  const CARD = (t, rowsHtml) => '<div class="tpl-card"><div class="tpl-card-h">' + t + '</div>' + rowsHtml + '</div>';
  const CROW = (label, field) => '<div class="tpl-card-r"><span class="tpl-card-l">' + label + '</span><span class="tpl-card-f">' + field + '</span></div>';
  const CROW2 = (label, field) => '<div class="tpl-card-r2"><span class="tpl-card-l">' + label + '</span><span class="tpl-card-f">' + field + '</span></div>';

  /* ===== 对外: 组装 head + body ===== */
  TPL.build = function (key, patient) {
    const p = patient || {};
    if (key === 'admission_assessment') {
      const body =
        CARD('一、入院原因', CROW('入院方式', RADIO('adm_way', [['walk', '步行'], ['wheel', '轮椅'], ['stretcher', '平车'], ['other', '其他']]))
          + CROW('入院诊断', INP('主要诊断', '100%'))
          + CROW2('主诉与现病史', '<textarea class="tpl-ctl" rows="2" style="width:100%"></textarea>')
          + CROW2('既往史', '<textarea class="tpl-ctl" rows="2" style="width:100%"></textarea>'))
        + CARD('二、生命体征', CROW('体温(℃)', NUM(56) + ' 途径 ' + RADIO('tp_way', [['ax', '腋'], ['oral', '口'], ['an', '肛'], ['ear', '耳']]))
          + CROW('脉搏(次/分)', NUM(56) + ' 心率(次/分) ' + NUM(56) + ' 呼吸(次/分) ' + NUM(56))
          + CROW('血压(mmHg)', NUM(50) + ' / ' + NUM(50) + ' 血氧(%) ' + NUM(50) + ' 疼痛评分 ' + NUM(50)))
        + CARD('三、身体评估', CROW('意识', RADIO('con', [['1', '清醒'], ['2', '嗜睡'], ['3', '昏睡'], ['4', '昏迷']]))
          + CROW('瞳孔', INP('等大等圆 / 对光反射', '100%'))
          + CROW('皮肤', CHK('skin', [['1', '完整'], ['2', '破损'], ['3', '压疮'], ['4', '皮疹'], ['5', '水肿']]))
          + CROW('过敏史', INP('药物/食物过敏史，无则填"无"', '100%'))
          + CROW('管路', CHK('pipe', [['1', '无'], ['2', '胃管'], ['3', '尿管'], ['4', '引流管'], ['5', '深静脉']])))
        + CARD('四、生活自理', CROW('进食', RADIO('eat', [['1', '自理'], ['2', '协助'], ['3', '依赖']]))
          + CROW('排泄', RADIO('piss', [['1', '自理'], ['2', '协助'], ['3', '失禁']]))
          + CROW('活动', RADIO('move', [['1', '自如'], ['2', '协助'], ['3', '卧床']])))
        + CARD('五、评估意见', CROW2('护理评估意见', '<textarea class="tpl-ctl" rows="2" style="width:100%"></textarea>')
          + CROW('评估护士', INP('', '90px') + ' 评估时间 ' + INP('', '110px') + ' 复核人 ' + INP('', '90px')));
      return { title: '入院评估单', head: sheetHead('入院评估单') + patientBar(p), body: body };
    }
    if (key === 'nursing_plan') {
      let rows = '';
      for (let i = 1; i <= 12; i++) {
        rows += ROW([TD(i, ' class="tpl-seq"'), TD('<input class="tpl-ctl" style="width:90%">'),
          TD('<textarea class="tpl-ctl" rows="1" style="width:100%"></textarea>'),
          TD('<input class="tpl-ctl" style="width:100%">'), TD('<input class="tpl-ctl" style="width:100%">'),
          TD('<input class="tpl-ctl" style="width:100%">'), TD('<input class="tpl-ctl" style="width:90%">')]);
      }
      const body = '<table class="sheet-table tpl-plan">'
        + ROW([TH('序号', '6%'), TH('开始日期', '12%'), TH('护理诊断/问题'), TH('护理目标', '18%'), TH('护理措施', '24%'), TH('效果评价', '15%'), TH('停止日期', '10%')])
        + rows
        + ROW([LBL('制定护士'), TD(INP('', '80%'), ' colspan="3"'), LBL('修订日期', '12%'), TD(INP('', '80%'), ' colspan="2"')])
        + '</table>';
      return { title: '护理计划单', head: sheetHead('护理计划单') + patientBar(p), body: body };
    }
    if (key === 'health_edu') {
      const items = [['入院宣教', '病区环境/作息/探视制度/主管医护'], ['饮食指导', '治疗饮食种类与注意事项'],
        ['用药指导', '口服药用法/不良反应观察'], ['检查指导', '检查前准备/注意事项'],
        ['活动与康复指导', '活动强度/康复锻炼'], ['安全防护', '防跌倒/防坠床/管道安全'],
        ['心理疏导', '情绪支持/家属沟通'], ['出院指导', '复诊/用药/家庭护理']];
      let rows = '';
      items.forEach(function (it, i) {
        rows += ROW([TD(i + 1, ' class="tpl-seq"'), TD('<b>' + it[0] + '</b>'), TD(it[1]),
          TD(CHK('m' + i, [['y', '口头'], ['b', '书面'], ['s', '演示']])), TD(RADIO('o' + i, [['p', '患者'], ['f', '家属']])),
          TD(RADIO('r' + i, [['y', '掌握'], ['p', '部分'], ['n', '未掌握']])), TD(INP('宣教者/日期', '90%'))]);
      });
      const body = '<table class="sheet-table">'
        + ROW([TH('序号', '5%'), TH('宣教项目', '13%'), TH('宣教要点'), TH('方式', '16%'), TH('对象', '11%'), TH('评价', '12%'), TH('宣教者/日期', '16%')])
        + rows
        + ROW([LBL('补充记录'), TD('<textarea class="tpl-ctl" rows="2" style="width:100%"></textarea>', ' colspan="6"')])
        + ROW([LBL('责任护士'), TD(INP('', '80%'), ' colspan="3"'), LBL('护士长抽查', '12%'), TD(INP('', '80%'), ' colspan="2"')])
        + '</table>';
      return { title: '健康宣教单', head: sheetHead('健康宣教单') + patientBar(p), body: body };
    }
    if (key === 'transfer_record') {
      const body =
        CARD('一、转运信息', CROW('转出病区', INP('', '90px') + ' → 转入病区 ' + INP('', '90px'))
          + CROW('转出时间', INP('', '110px') + ' 转运方式 ' + RADIO('tway', [['1', '轮椅'], ['2', '平车'], ['3', '步行']]) + ' 转运人员 ' + INP('', '90px')))
        + CARD('二、转运前评估', CROW('生命体征', '体温 ' + NUM(50) + '℃ 脉搏 ' + NUM(50) + ' 呼吸 ' + NUM(50) + ' 血压 ' + NUM(50) + '/' + NUM(50) + ' 血氧 ' + NUM(50) + '%')
          + CROW('意识', RADIO('con', [['1', '清醒'], ['2', '嗜睡'], ['3', '昏睡'], ['4', '昏迷']]))
          + CROW('管路', CHK('pipe', [['1', '无'], ['2', '胃管'], ['3', '尿管'], ['4', '引流管'], ['5', '深静脉']]) + ' 输液 ' + RADIO('inf', [['y', '有'], ['n', '无']]))
          + CROW('皮肤', RADIO('skin', [['1', '完整'], ['2', '破损'], ['3', '压疮']]))
          + CROW2('引流情况', '<textarea class="tpl-ctl" rows="1" style="width:100%"></textarea>'))
        + CARD('三、转运交接', CROW2('携带物品/药品/资料', '<textarea class="tpl-ctl" rows="2" style="width:100%"></textarea>')
          + CROW2('途中病情变化及处理', '<textarea class="tpl-ctl" rows="2" style="width:100%"></textarea>')
          + CROW2('交接内容(病情/治疗/管路/皮肤)', '<textarea class="tpl-ctl" rows="3" style="width:100%"></textarea>'))
        + CARD('四、签名', CROW('转出护士', INP('', '90px') + ' 接收护士 ' + INP('', '90px') + ' 转运人员 ' + INP('', '90px')));
      return { title: '转运(转科)记录单', head: sheetHead('转运(转科)记录单') + patientBar(p), body: body };
    }
    /* 通用护理文书: 兼容任意输入的通用文书 (结构化卡片 + 自由横线) */
    const body =
      CARD('文书记录', CROW2('标题/主题', '<input class="tpl-ctl" style="width:100%" placeholder="文书主题">')
        + CROW2('正文内容', '<textarea class="tpl-ctl" rows="10" style="width:100%"></textarea>')
        + CROW2('', LINES(13)))
      + '<div class="tpl-card">' + CROW('记录护士', INP('', '90px') + ' 记录时间 ' + INP('', '110px') + ' 护士长审核 ' + INP('', '90px')) + '</div>';
    return { title: '通用护理文书', head: sheetHead('通用护理文书') + patientBar(p), body: body };
  };
})();