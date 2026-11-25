// public/js/nurse/print/nurse_print.js - 护理记录单横板 A4 打印文档组装
// 布局 (参照临床横板护理记录单): 日期 | 时间 | 生命体征(体温/脉搏/呼吸/血压/血氧/意识/瞳孔/GCS)
//   | 入量/出量/小便量/大便 | 病情观察及措施 | 记录人; 日期每显示日仅一次;
// 病情观察及措施自动拼装: 护理记录正文 + 表格未独立成列且已填写的字段
//   (管路/引流/血糖/疼痛/心率/降温复测/皮试/过敏药物/特殊药物) + 其他备注 ("其他：xxx");
// 行高按内容估算自动分页, 每页完整表头, 页脚页码; 严禁超出横板 A4 (1123×794)。
(function () {
  'use strict';
  const NP = (window.NursePrint = {});

  /* ===== 横板 A4 尺寸常量 ===== */
  const PAGE_W = 1123, PAGE_H = 794;
  const COLS = { date: 64, time: 48, vitals: [40, 44, 44, 58, 40, 44, 58, 42], io: [46, 46, 46, 42], sign: 52 };
  const FIXED_W = COLS.date + COLS.time + COLS.vitals.reduce((a, b) => a + b, 0) + COLS.io.reduce((a, b) => a + b, 0) + COLS.sign;
  const NARR_W = PAGE_W - 48 - FIXED_W;            // 病情观察列宽 (页面减左右内边距)
  const NARR_CPL = Math.floor((NARR_W - 12) / 10.5) - 2;   // 病情观察每行字数 (10.5px CJK 估算, 留 10% 余量)
  const PUPIL_CPL = Math.floor((COLS.vitals[6] - 8) / 10);
  const ROW_LINE_H = 15, ROW_PAD = 10, ROW_MIN_H = 26, SUM_ROW_H = 24;
  const HEAD_H = 70, THEAD_H = 44, PAGE_PAD = 34, FOOT_H = 22;
  const BODY_BUDGET = PAGE_H - PAGE_PAD - HEAD_H - THEAD_H - FOOT_H;   // 每页表体可用高度
  const TYPE_NAME = { oral: '口温', axillary: '腋温', rectal: '肛温', ear: '耳温' };

  const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
  const nv = function (v) { return (v === null || v === undefined || v === '') ? '' : esc(v); };

  /* ===== 病情观察及措施自动拼装: 正文 → 未独立成列的已填字段 → 其他备注 ===== */
  NP.buildNarrative = function (r) {
    const main = String(r.note || r.content || '').trim();
    const segs = [];
    if (r.is_first == 1) segs.push('首次护理记录');
    if (r.has_pipeline) segs.push('管路：' + esc(r.has_pipeline) + (r.pipeline_desc ? '（' + esc(r.pipeline_desc) + '）' : ''));
    if (r.drainage_ml != null) segs.push('引流量 ' + esc(r.drainage_ml) + 'ml');
    if (r.blood_glucose != null) segs.push('血糖 ' + esc(r.blood_glucose) + 'mmol/L');
    if (r.pain_score != null) segs.push('疼痛评分 ' + esc(r.pain_score) + '分');
    if (r.heart_rate != null && String(r.heart_rate) !== String(r.pulse)) segs.push('心率 ' + esc(r.heart_rate) + '次/分');
    if (r.cooling_temp != null) segs.push('降温后复测 ' + esc(r.cooling_temp) + '℃');
    if (r.temp_type && r.temp_type !== 'axillary' && r.temperature != null) segs.push((TYPE_NAME[r.temp_type] || '') + ' ' + esc(r.temperature) + '℃');
    if (r.skin_test) segs.push(r.skin_test.indexOf('阳性') >= 0 ? '<span class="np-pos">皮试' + esc(r.skin_test) + '</span>' : '皮试' + esc(r.skin_test));
    if (r.allergy_drugs) segs.push('过敏药物：' + esc(r.allergy_drugs));
    if (r.special_drugs) segs.push('特殊药物：' + esc(r.special_drugs));
    let html = esc(main);
    if (segs.length) html += (/[。.！!？?]$/.test(main) ? '' : '。') + segs.join('；') + '。';
    if (r.other_remark) html += '其他：' + esc(r.other_remark) + '。';
    return html;
  };
  /* 病情观察纯文本长度 (分页行高估算用, 不含 HTML 标签) */
  function narrativeLen(r) {
    return String(r.note || r.content || '').trim().length
        + (r.is_first == 1 ? 6 : 0)
        + (r.has_pipeline ? 6 + String(r.pipeline_desc || '').length : 0)
        + (r.drainage_ml != null ? 9 : 0)
        + (r.blood_glucose != null ? 12 : 0)
        + (r.pain_score != null ? 9 : 0)
        + (r.heart_rate != null && String(r.heart_rate) !== String(r.pulse) ? 10 : 0)
        + (r.cooling_temp != null ? 13 : 0)
        + (r.temp_type && r.temp_type !== 'axillary' && r.temperature != null ? 10 : 0)
        + (r.skin_test ? 6 : 0)
        + (r.allergy_drugs ? 8 + String(r.allergy_drugs).length : 0)
        + (r.special_drugs ? 8 + String(r.special_drugs).length : 0)
        + (r.other_remark ? 5 + String(r.other_remark).length : 0);
  }

  /* ===== 行高估算 (自动分页) ===== */
  function rowHeight(r) {
    const lines = Math.max(
      Math.max(1, Math.ceil(narrativeLen(r) / Math.max(10, NARR_CPL))),
      r.pupil ? Math.ceil(String(r.pupil).length / Math.max(2, PUPIL_CPL)) : 1,
      r.consciousness ? Math.ceil(String(r.consciousness).length / 3) : 1
    );
    return Math.max(ROW_MIN_H, lines * ROW_LINE_H + ROW_PAD);
  }

  /* ===== 日期/时间拆分 ===== */
  function dayOf(r) { return String(r.record_time || '').slice(0, 10); }
  function timeOf(r) { return String(r.record_time || '').slice(11, 16); }
  function cnDate(d) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return esc(d);
    return parseInt(d.slice(5, 7), 10) + '月' + parseInt(d.slice(8, 10), 10) + '日';
  }

  /* ===== 渲染行 ===== */
  function renderRow(r, showDate) {
    const gcs = (r.gcs_eye != null || r.gcs_verbal != null || r.gcs_motor != null)
      ? [r.gcs_eye, r.gcs_verbal, r.gcs_motor].map(function (v) { return v == null ? '-' : v; }).join('/') : '';
    let h = '<tr>';
    h += '<td class="npr-date">' + (showDate ? cnDate(dayOf(r)) : '') + '</td>';
    h += '<td class="npr-time">' + esc(timeOf(r)) + '</td>';
    h += '<td class="npr-v">' + nv(r.temperature != null ? Number(r.temperature).toFixed(1).replace(/\.0$/, '') : null) + '</td>';
    h += '<td class="npr-v">' + nv(r.pulse) + '</td>';
    h += '<td class="npr-v">' + nv(r.respiratory_rate) + '</td>';
    h += '<td class="npr-v">' + (r.bp_systolic != null ? esc(r.bp_systolic) + '/' + esc(r.bp_diastolic) : '') + '</td>';
    h += '<td class="npr-v">' + nv(r.oxygen_saturation) + '</td>';
    h += '<td class="npr-v">' + nv(r.consciousness) + '</td>';
    h += '<td class="npr-v npr-pupil">' + nv(r.pupil) + '</td>';
    h += '<td class="npr-v">' + esc(gcs) + '</td>';
    h += '<td class="npr-v">' + nv(r.intake_ml) + '</td>';
    h += '<td class="npr-v">' + nv(r.output_ml) + '</td>';
    h += '<td class="npr-v">' + nv(r.urine_ml) + '</td>';
    h += '<td class="npr-v">' + nv(r.stool) + '</td>';
    h += '<td class="npr-narr">' + NP.buildNarrative(r) + '</td>';
    h += '<td class="npr-sign">' + nv(r.recorder_name) + '</td></tr>';
    return h;
  }
  function renderSumRow(day) {
    const fmt = function (v) { return v > 0 ? String(Math.round(v * 10) / 10).replace(/\.0$/, '') : ''; };
    let h = '<tr class="npr-sum"><td class="npr-date"></td><td class="npr-time">本日合计</td>';
    for (let i = 0; i < 8; i++) h += '<td class="npr-v"></td>';
    h += '<td class="npr-v">' + fmt(day.intake) + '</td>';
    h += '<td class="npr-v">' + fmt(day.output) + '</td>';
    h += '<td class="npr-v">' + fmt(day.urine) + '</td>';
    h += '<td class="npr-v">' + esc(day.stool || '') + '</td>';
    h += '<td class="npr-narr"></td><td class="npr-sign"></td></tr>';
    return h;
  }

  /* ===== 表头 (两行: 分组行 + 列名行) ===== */
  function theadHtml() {
    const vCols = ['体温℃', '脉搏<br>次/分', '呼吸<br>次/分', '血压<br>mmHg', '血氧<br>%', '意识', '瞳孔', 'GCS'];
    const ioCols = ['入量<br>ml', '出量<br>ml', '小便<br>ml', '大便'];
    let h = '<colgroup><col style="width:' + COLS.date + 'px"><col style="width:' + COLS.time + 'px">';
    COLS.vitals.forEach(function (w) { h += '<col style="width:' + w + 'px">'; });
    COLS.io.forEach(function (w) { h += '<col style="width:' + w + 'px">'; });
    h += '<col><col style="width:' + COLS.sign + 'px"></colgroup>';
    h += '<thead><tr>'
        + '<th rowspan="2" class="npr-h">日期</th><th rowspan="2" class="npr-h">时间</th>'
        + '<th colspan="8" class="npr-h npr-group">生命体征</th>'
        + '<th colspan="4" class="npr-h npr-group">出入量（ml）</th>'
        + '<th rowspan="2" class="npr-h npr-narr-h">病情观察护理措施效果及判断</th>'
        + '<th rowspan="2" class="npr-h">记录人</th></tr><tr>';
    vCols.forEach(function (c) { h += '<th class="npr-h">' + c + '</th>'; });
    ioCols.forEach(function (c) { h += '<th class="npr-h">' + c + '</th>'; });
    return h + '</tr></thead>';
  }

  /* ===== 行按日分组 (保序) + 本日合计 ===== */
  function buildRenderList(rows) {
    const list = [];
    let cur = null;
    rows.forEach(function (r) {
      const d = dayOf(r);
      if (!cur || cur.date !== d) {
        if (cur) list.push({ type: 'sum', day: cur });
        cur = { date: d, rows: [], intake: 0, output: 0, urine: 0, stool: '' };
        list.push({ type: 'day', date: d });
      }
      cur.rows.push(r);
      if (r.intake_ml != null) cur.intake += parseFloat(r.intake_ml) || 0;
      if (r.output_ml != null) cur.output += parseFloat(r.output_ml) || 0;
      if (r.urine_ml != null) cur.urine += parseFloat(r.urine_ml) || 0;
      if (r.stool) cur.stool = r.stool;
      list.push({ type: 'row', note: r, showDate: cur.rows.length === 1 });
    });
    if (cur) list.push({ type: 'sum', day: cur });
    return list;
  }

  /* ===== 按高度预算分页 ===== */
  function paginate(list) {
    const pages = [];
    let page = [], used = 0;
    list.forEach(function (item) {
      const h = item.type === 'row' ? rowHeight(item.note) : SUM_ROW_H;
      if (used + h > BODY_BUDGET && page.length) { pages.push(page); page = []; used = 0; }
      page.push(item);
      used += h;
    });
    if (page.length) pages.push(page);
    return pages;
  }

  /* ===== 对外: 生成横板 A4 页面文档数组 ===== */
  NP.notesLandscapePages = function (rows, patient) {
    const list = buildRenderList((rows || []).slice());
    if (!list.length) return [];
    const info = PrintPreview.patientInfo(patient || {}, '护理记录单');
    const pages = paginate(list);
    return pages.map(function (page, idx) {
      return pageDoc(page, info, idx + 1, pages.length);
    });
  };

  function pageDoc(items, info, pageNo, pageCount) {
    let body = '';
    items.forEach(function (item) {
      if (item.type === 'row') body += renderRow(item.note, item.showDate);
      else if (item.type === 'sum') body += renderSumRow(item.day);
    });
    const header = (typeof PrintPreview !== 'undefined' && PrintPreview.sheetHeader)
      ? PrintPreview.sheetHeader(info) : '';
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>护理记录单</title>'
        + '<style>' + landscapeCss() + '</style></head><body>'
        + '<div class="page npr-page">'
        + '<div id="patient-header-slot">' + header + '</div>'
        + '<table class="npr-table">' + theadHtml() + '<tbody>' + body + '</tbody></table>'
        + '<div class="npr-foot"><span class="emr-fp-page">第 ' + pageNo + ' 页 / 共 ' + pageCount + ' 页</span></div>'
        + '</div></body></html>';
  }

  /* ===== 横板 A4 样式 ===== */
  function landscapeCss() {
    return [
      '@page { size: A4 landscape; margin: 0; }',
      'html, body { margin: 0; padding: 0; background: #fff; }',
      '* { box-sizing: border-box; print-color-adjust: exact; -webkit-print-color-adjust: exact; }',
      '.npr-page { position: relative; width: ' + PAGE_W + 'px; height: ' + PAGE_H + 'px; margin: 0 auto; background: #fff; padding: 14px 24px 18px; overflow: hidden; font-family: "Helvetica Neue", Arial, "PingFang SC", "Microsoft YaHei", sans-serif; }',
      '#patient-header-slot { margin-bottom: 4px; text-align: center; }',
      '.pg-head { text-align: center; }',
      '.brand-name-lockup { display: inline-grid; grid-template-columns: minmax(0,1fr); width: max-content; max-width: 100%; min-width: 0; }',
      '.brand-name-line { width: 100%; min-width: 0; text-align: justify; text-align-last: justify; text-justify: inter-character; }',
      '.emr-brand-lockup { display: grid; margin: 0 auto; }',
      '.emr-hos { font-size: 14pt; font-weight: bold; letter-spacing: 3px; line-height: 1.25; }',
      '.emr-hos2 { font-size: 9pt; font-weight: normal; line-height: 1.2; }',
      '.emr-docname { font-size: 11pt; font-weight: bold; letter-spacing: 3px; line-height: 1.3; }',
      '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1px 14px; margin-top: 4px; padding: 3px 4px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 8.5pt; }',
      '.emr-hf { display: inline-flex; gap: 3px; }',
      '.emr-hf b { font-weight: 600; }',
      '.emr-hf-val { min-width: 30px; text-align: center; }',
      '.npr-table { width: 100%; border-collapse: collapse; table-layout: fixed; }',
      '.npr-table th, .npr-table td { border: 0.5pt solid #000; padding: 3px 4px; vertical-align: top; text-align: left; font-size: 9pt; line-height: 1.4; overflow: hidden; word-break: break-all; }',
      '.npr-table th.npr-h { background: #f0f2f5; text-align: center; font-weight: 600; vertical-align: middle; font-size: 9pt; line-height: 1.3; }',
      '.npr-group { letter-spacing: 8px; padding-left: 8px; }',
      '.npr-date, .npr-time { text-align: center; white-space: nowrap; font-size: 9pt; }',
      '.npr-v { text-align: center; font-size: 9pt; }',
      '.npr-pupil { text-align: left; }',
      '.npr-narr { font-size: 10pt; line-height: 1.45; }',
      '.npr-sign { text-align: center; }',
      '.npr-sum td { background: #f7f9fb; font-weight: 600; text-align: center; }',
      '.npr-pos { color: #dc2626; font-weight: 700; }',
      '.npr-foot { position: absolute; bottom: 8px; left: 0; right: 0; text-align: center; font-size: 9.5pt; color: #303133; }'
    ].join('\n');
  }
})();
