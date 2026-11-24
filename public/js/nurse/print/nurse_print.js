// public/js/nurse/print/nurse_print.js - 护理记录单/体温单 A4 打印文档组装
// 抬头与电子病历一致 (医院名+文书名+患者信息栏); 内容按行/网格呈现
// build(rows, patient, type): 'notes' 护理记录单 | 'temp' 体温单
(function () {
  'use strict';
  const NP = (window.NursePrint = {});
  const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
  const nv = function (v) { return (v === null || v === undefined || v === '') ? '' : esc(v); };

  /* 护理记录单 A4: 一行一个时间段 (时间 / 生命体征 / 护理记录内容) */
  NP.notesHtml = function (rows, patient) {
    const info = PrintPreview.patientInfo(patient, '护理记录单');
    const header = PrintPreview.sheetHeader(info);
    const body = rows.map(function (r) {
      const time = (r.record_time || '').slice(0, 16);
      const vitals = [];
      if (r.temperature != null) vitals.push('体温 ' + nv(r.temperature) + '℃');
      if (r.pulse != null) vitals.push('脉搏 ' + nv(r.pulse));
      if (r.respiratory_rate != null) vitals.push('呼吸 ' + nv(r.respiratory_rate));
      if (r.bp_systolic != null) vitals.push('血压 ' + nv(r.bp_systolic) + '/' + nv(r.bp_diastolic));
      if (r.oxygen_saturation != null) vitals.push('血氧 ' + nv(r.oxygen_saturation) + '%');
      if (r.intake_ml != null) vitals.push('入量 ' + nv(r.intake_ml) + 'ml');
      if (r.output_ml != null) vitals.push('出量 ' + nv(r.output_ml) + 'ml');
      const notes = [];
      if (r.has_pipeline) notes.push('管路 ' + nv(r.has_pipeline) + (r.pipeline_desc ? '(' + nv(r.pipeline_desc) + ')' : ''));
      if (r.drainage_ml != null) notes.push('引流 ' + nv(r.drainage_ml) + 'ml');
      if (r.consciousness) notes.push('意识 ' + nv(r.consciousness));
      if (r.blood_glucose != null) notes.push('血糖 ' + nv(r.blood_glucose));
      if (r.pupil) notes.push('瞳孔 ' + nv(r.pupil));
      if (r.gcs_eye != null) notes.push('GCS ' + nv(r.gcs_eye) + '/' + nv(r.gcs_verbal) + '/' + nv(r.gcs_motor));
      if (r.is_first == 1) notes.push('首次护理记录');
      const meta = vitals.concat(notes).join('　');
      const cat = r.category === 'first' ? '首次' : { observation: '病情观察', measure: '测量', care: '护理措施', medication: '给药', transfer: '转科', other: '其他' }[r.category] || r.category;
      return '<tr>'
        + '<td class="np-time">' + esc(time) + '</td>'
        + '<td class="np-cat">' + esc(cat) + '</td>'
        + '<td class="np-meta">' + meta + '</td>'
        + '<td class="np-note">' + nv(r.note || r.content) + '</td>'
        + '<td class="np-sign">' + nv(r.recorder_name) + '</td></tr>';
    }).join('');
    return NP.docHtml(info, header,
      '<table class="np-table">'
      + '<thead><tr><th style="width:9%">时间</th><th style="width:6%">类别</th><th style="width:22%">生命体征/观察</th><th style="width:53%">护理记录</th><th style="width:10%">护士签名</th></tr></thead>'
      + '<tbody>' + body + '</tbody></table>');
  };

  /* 体温单 A4 打印已升级为标准三测单 (TempChart, js/nurse/print/temperature_chart.js) */

  /* 组装完整 A4 文档 (页眉+正文+页脚页码) */
  NP.docHtml = function (info, headerHtml, bodyHtml) {
    const sheet = '<div class="page">'
      + headerHtml
      + '<div class="pg-body">' + bodyHtml + '</div>'
      + '<div class="pg-foot"><span class="emr-fp-left">' + esc(info.hospital || '') + '</span>'
      + '<span class="emr-fp-page">第 1 页 / 共 1 页</span></div></div>';
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + esc(info.doc_name || '护理文书') + '</title>'
      + '<style>' + NP.printCss() + '</style></head><body>' + sheet + '</body></html>';
  };

  NP.printCss = function () {
    return [
      '@page { size: A4; margin: 0; }',
      'html, body { margin: 0; padding: 0; }',
      '* { box-sizing: border-box; }',
      'body { background: #e9edf2; }',
      '.page { position: relative; width: 794px; height: 1123px; overflow: hidden; margin: 0 auto; background: #fff; }',
      '.pg-head { position: absolute; left: 36px; right: 36px; top: 20px; text-align: center; }',
      '.brand-name-lockup { display:inline-grid; grid-template-columns:minmax(0,1fr); width:max-content; max-width:100%; min-width:0; }',
      '.brand-name-line { width:100%; min-width:0; text-align:justify; text-align-last:justify; text-justify:inter-character; }',
      '.emr-brand-lockup { display:grid; margin:0 auto; }',
      '.emr-hos { font-size: 20pt; font-weight: bold; letter-spacing: 4px; line-height: 1.4; }',
      '.emr-hos2 { font-size:12pt; font-weight:normal; line-height:1.35; }',
      '.emr-docname { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.5; }',
      '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 20px; margin-top: 8px; padding: 6px 4px 6px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 11pt; }',
      '.pg-body { position: absolute; left: 36px; top: 158px; width: 722px; height: 906px; overflow: hidden; }',
      '.np-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10.5pt; }',
      '.np-table th, .np-table td { border: 0.5pt solid #000; padding: 5px 6px; vertical-align: top; text-align: left; }',
      '.np-table th { background: #f2f2f2; text-align: center; font-weight: 600; }',
      '.np-time { white-space: nowrap; }',
      '.np-cat { text-align: center; }',
      '.np-meta { font-size: 9.5pt; line-height: 1.5; }',
      '.np-note { font-size: 10.5pt; line-height: 1.6; }',
      '.np-sign { text-align: center; }',
      '.np-temp .np-td { text-align: center; font-size: 9.5pt; line-height: 1.6; height: 56px; }',
      '.np-temp .np-td-empty { text-align: center; color: #999; }',
      '.np-date { text-align: center; font-weight: 600; }',
      '.pg-foot { position: absolute; left: 36px; right: 36px; bottom: 22px; height: 30px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt; color: #000; }',
      '.pg-foot .emr-fp-page { position: absolute; left: 0; right: 0; bottom: 0; text-align: center; }'
    ].join('\n');
  };
})();
