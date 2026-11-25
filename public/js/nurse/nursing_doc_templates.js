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

  /* ===== 对外: 组装 head + body ===== */
  TPL.build = function (key, patient) {
    const p = patient || {};
    if (key === 'admission_assessment') {
      const body = '<table class="sheet-table">'
        + secTitle('一、主诉与病史')
        + blankRow('主诉')
        + multiBlank('现病史', 2, 54)
        + multiBlank('既往史', 2, 44)
        + secTitle('二、生命体征')
        + row([labelTd('体温(℃)'), td(''), labelTd('脉搏(次/分)'), td(''), labelTd('呼吸(次/分)'), td('')])
        + row([labelTd('血压(mmHg)'), td(''), labelTd('血氧(%)'), td(''), labelTd('疼痛评分'), td('')])
        + secTitle('三、身体状况评估')
        + row([labelTd('意识'), td(''), labelTd('瞳孔'), td('', ' colspan="3"')])
        + row([labelTd('皮肤状况'), td(''), labelTd('过敏史'), td('', ' colspan="3"')])
        + row([labelTd('管路情况'), td(''), labelTd('管路描述'), td('', ' colspan="3"')])
        + row([labelTd('饮食'), td(''), labelTd('睡眠'), td(''), labelTd('排泄'), td('')])
        + row([labelTd('自理能力'), td(''), labelTd('跌倒风险'), td(''), labelTd('压力性损伤风险'), td('')])
        + secTitle('四、心理社会与宣教')
        + multiBlank('心理状况', 2, 42)
        + multiBlank('健康教育需求', 2, 42)
        + secTitle('五、评估意见')
        + multiBlank('护理评估意见', 2, 54)
        + row([labelTd('评估护士'), td(''), labelTd('评估时间'), td(''), labelTd('复核人'), td('')])
        + '</table>';
      return { title: '入院评估单', head: sheetHead('入院评估单') + patientBar(p), body: body };
    }
    if (key === 'nursing_plan') {
      const rows = [];
      for (let i = 1; i <= 12; i++) {
        rows.push(row([td(i, ' class="tpl-seq"'), td(''), td('', ' style="height:46px"'), td('', ' style="height:46px"'),
          td('', ' style="height:46px"'), td(''), td('')]));
      }
      const body = '<table class="sheet-table">'
        + '<tr><th style="width:6%">序号</th><th style="width:12%">开始日期</th><th>护理诊断/问题</th>'
        + '<th style="width:18%">护理目标</th><th style="width:24%">护理措施</th><th style="width:15%">效果评价</th><th style="width:10%">停止日期</th></tr>'
        + rows.join('')
        + row([labelTd('制定护士'), td('', ' colspan="3"'), labelTd('修订日期'), td('', ' colspan="2"')])
        + '</table>';
      return { title: '护理计划单', head: sheetHead('护理计划单') + patientBar(p), body: body };
    }
    if (key === 'health_edu') {
      const items = ['入院宣教', '饮食指导', '用药指导', '检查指导', '活动与康复指导', '安全防护', '心理疏导', '出院指导'];
      const rows = items.map((it, i) => row([
        td(i + 1, ' class="tpl-seq"'), td('<b>' + it + '</b>'), td('', ' style="height:44px"'),
        td('', ' style="width:12%"'), td('', ' style="width:10%"'), td('', ' style="width:10%"'), td('', ' style="width:16%"')
      ]));
      const body = '<table class="sheet-table">'
        + '<tr><th style="width:5%">序号</th><th style="width:13%">宣教项目</th><th>宣教内容</th>'
        + '<th style="width:12%">宣教方式</th><th style="width:10%">对象</th><th style="width:10%">评价</th><th style="width:16%">宣教者/日期</th></tr>'
        + rows.join('')
        + multiBlank('补充宣教记录', 3, 46)
        + row([labelTd('责任护士'), td('', ' colspan="3"'), labelTd('护士长抽查'), td('', ' colspan="2"')])
        + '</table>';
      return { title: '健康宣教单', head: sheetHead('健康宣教单') + patientBar(p), body: body };
    }
    if (key === 'transfer_record') {
      const body = '<table class="sheet-table">'
        + secTitle('一、转运信息')
        + row([labelTd('转出病区'), td(''), labelTd('转入病区'), td('', ' colspan="3"')])
        + row([labelTd('转出时间'), td(''), labelTd('转运方式'), td('', ' colspan="3"')])
        + secTitle('二、转运前评估')
        + row([labelTd('意识'), td(''), labelTd('生命体征'), td('', ' colspan="3"')])
        + row([labelTd('体温(℃)'), td(''), labelTd('脉搏(次/分)'), td(''), labelTd('呼吸(次/分)'), td('')])
        + row([labelTd('血压(mmHg)'), td(''), labelTd('血氧(%)'), td(''), labelTd('疼痛评分'), td('')])
        + row([labelTd('管路'), td(''), labelTd('管路描述'), td('', ' colspan="3"')])
        + row([labelTd('皮肤状况'), td(''), labelTd('输液情况'), td('', ' colspan="3"')])
        + row([labelTd('引流情况'), td('', ' colspan="5"')])
        + secTitle('三、转运交接')
        + multiBlank('携带物品/药品/资料', 2, 46)
        + multiBlank('途中病情变化及处理', 3, 46)
        + multiBlank('交接内容(病情/治疗/管路/皮肤)', 3, 46)
        + secTitle('四、签名')
        + row([labelTd('转出护士'), td(''), labelTd('接收护士'), td(''), labelTd('接收时间'), td('')])
        + '</table>';
      return { title: '转运(转科)记录单', head: sheetHead('转运(转科)记录单') + patientBar(p), body: body };
    }
    /* 通用护理文书: 兼容任意输入的自由文书 */
    const body = '<table class="sheet-table">'
      + multiBlank('护理文书内容', 15, 56)
      + row([labelTd('记录护士'), td(''), labelTd('护士长审核'), td('', ' colspan="3"')])
      + '</table>';
    return { title: '通用护理文书', head: sheetHead('通用护理文书') + patientBar(p), body: body };
  };
})();