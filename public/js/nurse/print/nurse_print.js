// public/js/nurse/print/nurse_print.js - 护理记录单横板 A4 打印文档组装
// 布局 (参照临床横板护理记录单): 日期 | 时间 | 生命体征(体温/脉搏/呼吸/血压/血氧/意识/瞳孔/GCS)
//   | 入量/出量/小便量/大便 | 病情观察及措施 | 记录人; 日期每显示日仅一次;
// 病情观察及措施自动拼装: 护理记录正文 + 表格未独立成列且已填写的字段
//   (管路/引流/血糖/疼痛/心率/降温复测/皮试/过敏药物/特殊药物) + 其他备注 ("其他：xxx");
// 分页: 在隐藏 iframe 中以与打印文档完全一致的 CSS/列宽实测行高 (不依赖字体字号估算);
//   行高超出当页剩余空间时对病情观察列做字符级二分拆行 — 前段本页显示, 后段次页续显
//   (续行行仅填病情观察列, 日期/时间/记录人留空), 直至整行放下, 短行整体移次页,
//   每页空间利用最大化; 页眉每页完整, 页脚页码; 严禁超出横板 A4 (1123×794)。
(function () {
  'use strict';
  const NP = (window.NursePrint = {});

  /* ===== 横板 A4 尺寸常量 ===== */
  const PAGE_W = 1123, PAGE_H = 794;
  const COLS = { date: 64, time: 48, vitals: [40, 44, 44, 58, 40, 44, 58, 42], io: [46, 46, 46, 42], sign: 52 };
  const PAGE_PAD_TOP = 14, PAGE_PAD_BOTTOM = 18;    // .npr-page 内边距
  const HEADER_GAP = 4;                             // #patient-header-slot margin-bottom
  const FOOT_BOTTOM = 8, FOOT_GAP = 4;              // 页脚距页底 / 表体与页脚安全间距
  const MIN_HEAD_CHARS = 6, MIN_TAIL_CHARS = 4;     // 拆行前后段最小字符数
  const TYPE_NAME = { oral: '口温', axillary: '腋温', rectal: '肛温', ear: '耳温' };

  const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
  const nv = function (v) { return (v === null || v === undefined || v === '') ? '' : esc(v); };

  /* ===== 病情观察及措施: 分段结构 (纯文本 + 可选 cls), 支持字符级跨页拆分 ===== */
  /* 输出与旧版 buildNarrative 完全一致: 正文 → [。]拼装字段(；分隔)[。] → 其他：xxx。 */
  NP.buildNarrativeSegs = function (r) {
    const segs = [];
    const main = String(r.note || r.content || '').trim();
    const extra = [];
    if (r.is_first == 1) extra.push({ text: '首次护理记录' });
    if (r.has_pipeline) extra.push({ text: '管路：' + r.has_pipeline + (r.pipeline_desc ? '（' + r.pipeline_desc + '）' : '') });
    if (r.drainage_ml != null) extra.push({ text: '引流量 ' + r.drainage_ml + 'ml' });
    if (r.blood_glucose != null) extra.push({ text: '血糖 ' + r.blood_glucose + 'mmol/L' });
    if (r.pain_score != null) extra.push({ text: '疼痛评分 ' + r.pain_score + '分' });
    if (r.heart_rate != null && String(r.heart_rate) !== String(r.pulse)) extra.push({ text: '心率 ' + r.heart_rate + '次/分' });
    if (r.cooling_temp != null) extra.push({ text: '降温后复测 ' + r.cooling_temp + '℃' });
    if (r.temp_type && r.temp_type !== 'axillary' && r.temperature != null) extra.push({ text: (TYPE_NAME[r.temp_type] || '') + ' ' + r.temperature + '℃' });
    if (r.skin_test) extra.push(r.skin_test.indexOf('阳性') >= 0
      ? { text: '皮试' + r.skin_test, cls: 'np-pos' }
      : { text: '皮试' + r.skin_test });
    if (r.allergy_drugs) extra.push({ text: '过敏药物：' + r.allergy_drugs });
    if (r.special_drugs) extra.push({ text: '特殊药物：' + r.special_drugs });
    if (main) segs.push({ text: main });
    if (extra.length) {
      if (!/[。.！!？?]$/.test(main)) segs.push({ text: '。' });
      extra.forEach(function (s, i) { if (i) segs.push({ text: '；' }); segs.push(s); });
      segs.push({ text: '。' });
    }
    if (r.other_remark) segs.push({ text: '其他：' + r.other_remark + '。' });
    return segs;
  };
  function segsToHtml(segs) {
    return (segs || []).map(function (s) {
      return s.cls ? '<span class="' + s.cls + '">' + esc(s.text) + '</span>' : esc(s.text);
    }).join('');
  }
  function segsLen(segs) {
    let n = 0;
    for (let i = 0; i < segs.length; i++) n += segs[i].text.length;
    return n;
  }
  /* 按纯文本位置 k 拆分: 前段 head / 后段 tail (保留分段样式, 空段丢弃) */
  function splitSegs(segs, k) {
    const head = [], tail = [];
    let remain = k;
    for (let i = 0; i < segs.length; i++) {
      const s = segs[i];
      if (remain <= 0) { if (s.text) tail.push(s); continue; }
      if (s.text.length <= remain) {
        if (s.text) head.push(s);
        remain -= s.text.length;
      } else {
        head.push({ text: s.text.slice(0, remain), cls: s.cls });
        tail.push({ text: s.text.slice(remain), cls: s.cls });
        remain = 0;
      }
    }
    return { head: head, tail: tail };
  }
  NP.buildNarrative = function (r) { return segsToHtml(NP.buildNarrativeSegs(r)); };
  const EMPTY_SEGS = [];

  /* ===== 日期/时间拆分 ===== */
  function dayOf(r) { return String(r.record_time || '').slice(0, 10); }
  function timeOf(r) { return String(r.record_time || '').slice(11, 16); }
  function cnDate(d) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return esc(d);
    return parseInt(d.slice(5, 7), 10) + '月' + parseInt(d.slice(8, 10), 10) + '日';
  }

  /* ===== 渲染行 (contOnly=跨页续行: 仅病情观察列, 其余列留空) ===== */
  function renderRow(r, showDate, segs, contOnly) {
    const gcs = (r.gcs_eye != null || r.gcs_verbal != null || r.gcs_motor != null)
      ? [r.gcs_eye, r.gcs_verbal, r.gcs_motor].map(function (v) { return v == null ? '-' : v; }).join('/') : '';
    const narr = segs ? segsToHtml(segs) : NP.buildNarrative(r);
    let h = '<tr>';
    h += '<td class="npr-date">' + (contOnly ? '' : (showDate ? cnDate(dayOf(r)) : '')) + '</td>';
    h += '<td class="npr-time">' + (contOnly ? '' : esc(timeOf(r))) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.temperature != null ? Number(r.temperature).toFixed(1).replace(/\.0$/, '') : null)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.pulse)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.respiratory_rate)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : (r.bp_systolic != null ? esc(r.bp_systolic) + '/' + esc(r.bp_diastolic) : '')) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.oxygen_saturation)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.consciousness)) + '</td>';
    h += '<td class="npr-v npr-pupil">' + (contOnly ? '' : nv(r.pupil)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : esc(gcs)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.intake_ml)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.output_ml)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.urine_ml)) + '</td>';
    h += '<td class="npr-v">' + (contOnly ? '' : nv(r.stool)) + '</td>';
    h += '<td class="npr-narr">' + narr + '</td>';
    h += '<td class="npr-sign">' + (contOnly ? '' : nv(r.recorder_name)) + '</td></tr>';
    return h;
  }
  function renderSumRow(day) {
    const fmt = function (v) { return v > 0 ? String(Math.round(v * 10) / 10).replace(/\.0$/, '') : ''; };
    let h = '<tr class="npr-sum"><td colspan="2" class="npr-date">本日合计</td>';
    for (let i = 0; i < 8; i++) h += '<td class="npr-v"></td>';
    h += '<td class="npr-v">' + fmt(day.intake) + '</td>';
    h += '<td class="npr-v">' + fmt(day.output) + '</td>';
    h += '<td class="npr-v">' + fmt(day.urine) + '</td>';
    h += '<td class="npr-v">' + esc(day.stool || '') + '</td>';
    h += '<td class="npr-narr"></td><td class="npr-sign"></td></tr>';
    return h;
  }

  /* ===== 表头 (两行: 分组行 + 列名行, 含列宽 colgroup) ===== */
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

  /* ===== 实测度量上下文: 隐藏 iframe + 与打印文档同源 CSS/列宽, 行高/页眉/表头/页脚全部实测 ===== */
  function createMeasureContext(headerHtml) {
    try {
      if (!document.body) return null;
      const iframe = document.createElement('iframe');
      iframe.setAttribute('aria-hidden', 'true');
      iframe.style.cssText = 'position:fixed;left:-10000px;top:0;width:' + (PAGE_W + 60) + 'px;height:900px;visibility:hidden;border:0;';
      document.body.appendChild(iframe);
      const doc = iframe.contentDocument;
      if (!doc || !doc.body) { iframe.parentNode.removeChild(iframe); return null; }
      doc.open();
      doc.write('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><style>' + landscapeCss() + '</style></head><body>'
        + '<div class="npr-page" style="height:auto;overflow:visible;position:static;">'
        + '<div id="patient-header-slot">' + (headerHtml || '') + '</div>'
        + '<table class="npr-table">' + theadHtml() + '<tbody></tbody></table>'
        + '<div class="npr-foot" style="position:static;margin-top:' + FOOT_GAP + 'px;">第 1 页 / 共 1 页</div>'
        + '</div></body></html>');
      doc.close();
      const slot = doc.getElementById('patient-header-slot');
      const table = doc.querySelector('.npr-table');
      const tbody = table ? table.tBodies[0] : null;
      const foot = doc.querySelector('.npr-foot');
      if (!slot || !table || !tbody || !table.tHead || !foot) { iframe.parentNode.removeChild(iframe); return null; }
      const headerH = slot.offsetHeight;          // 实测页眉高
      const theadH = table.tHead.offsetHeight;    // 实测表头高
      const footH = foot.offsetHeight;            // 实测页脚高
      const contentBottom = Math.min(PAGE_H - PAGE_PAD_BOTTOM, PAGE_H - FOOT_BOTTOM - footH - FOOT_GAP);
      const budget = contentBottom - PAGE_PAD_TOP - (headerH + HEADER_GAP) - theadH;
      if (!(budget > 100)) { iframe.parentNode.removeChild(iframe); return null; }
      const rowRectH = function () {
        const tr = tbody.firstElementChild;
        return tr ? tr.getBoundingClientRect().height : 0;
      };
      let sumH = null;
      const ctx = {
        budget: budget,
        rowHeight: function (r, showDate, segs, contOnly) {
          tbody.innerHTML = renderRow(r, showDate, segs, contOnly);
          return rowRectH();
        },
        sumRowHeight: function (day) {
          if (sumH == null) { tbody.innerHTML = renderSumRow(day); sumH = rowRectH(); }
          return sumH;
        },
        /* 在 avail 高度内二分求病情观察可容纳的最大字符数 (返回 -1 表示空壳都放不下) */
        findSplitK: function (r, showDate, segs, contOnly, avail) {
          const total = segsLen(segs);
          const memo = {};
          const heightAt = function (k) {
            if (memo[k] !== undefined) return memo[k];
            tbody.innerHTML = renderRow(r, showDate, splitSegs(segs, k).head, contOnly);
            memo[k] = rowRectH();
            return memo[k];
          };
          if (heightAt(0) > avail) return -1;
          if (heightAt(total) <= avail) return total;
          let lo = 0, hi = total;
          while (lo < hi) {
            const mid = Math.ceil((lo + hi) / 2);
            if (heightAt(mid) <= avail) lo = mid; else hi = mid - 1;
          }
          let k = lo;                              // 防非严格单调: 逐字回退保证确实放得下
          while (k > 0 && heightAt(k) > avail) k--;
          return k;
        },
        dispose: function () { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }
      };
      return ctx;
    } catch (e) { return null; }
  }

  /* ===== 实测分页: 逐行装入, 放不下时对病情观察列拆行续页, 每页空间利用最大化 ===== */
  function paginateMeasured(list, ctx) {
    const pages = [];
    let cur = null, used = 0;
    const newPage = function () { cur = []; pages.push(cur); used = 0; };
    const pushItem = function (item, h) { if (!cur) newPage(); cur.push(item); used += h; };
    const q = list.slice();
    while (q.length) {
      const item = q.shift();
      if (item.type === 'sum') {
        const h = ctx.sumRowHeight(item.day);
        if (used + h > ctx.budget && used > 0) newPage();
        pushItem(item, h);
        continue;
      }
      const contOnly = !!item.cont;
      const segs = item.segs || NP.buildNarrativeSegs(item.note);
      const total = segsLen(segs);
      const hFull = ctx.rowHeight(item.note, item.showDate, segs, contOnly);
      if (hFull <= ctx.budget - used) { pushItem(item, hFull); continue; }
      /* 行放不下: 尝试对病情观察列做跨页拆行 */
      let handled = false;
      if (total >= MIN_HEAD_CHARS + MIN_TAIL_CHARS) {
        const shellH = ctx.rowHeight(item.note, item.showDate, EMPTY_SEGS, contOnly);
        let cap = ctx.budget - used;
        if (shellH > cap && used > 0) { newPage(); cap = ctx.budget; }   // 当页连空壳都放不下 → 次页再拆
        if (hFull > cap && shellH <= cap) {
          const k = ctx.findSplitK(item.note, item.showDate, segs, contOnly, cap);
          if (k >= MIN_HEAD_CHARS && total - k >= MIN_TAIL_CHARS) {
            const parts = splitSegs(segs, k);
            pushItem({ type: 'row', note: item.note, showDate: item.showDate, segs: parts.head },
                     ctx.rowHeight(item.note, item.showDate, parts.head, contOnly));
            newPage();
            q.unshift({ type: 'row', note: item.note, showDate: false, segs: parts.tail, cont: true });
            handled = true;
          }
        }
      }
      if (handled) continue;
      /* 不拆分: 整行移次页; 次页仍超高且不可拆 → 原样放置 (极端数据由页面裁剪兜底) */
      if (used > 0) newPage();
      pushItem(item, hFull);
    }
    return pages;
  }

  /* ===== 兜底: 度量上下文不可用时按字符估算分页 (与旧版行为一致, 不拆行) ===== */
  const FIXED_W = COLS.date + COLS.time + COLS.vitals.reduce(function (a, b) { return a + b; }, 0)
                + COLS.io.reduce(function (a, b) { return a + b; }, 0) + COLS.sign;
  const EST_CPL = Math.floor((PAGE_W - 48 - FIXED_W - 12) / 10.5) - 2;
  const EST_PUPIL_CPL = Math.floor((COLS.vitals[6] - 8) / 10);
  const EST_LINE_H = 15, EST_PAD = 10, EST_MIN_H = 26, EST_SUM_H = 24;
  const EST_BODY_BUDGET = PAGE_H - 34 - 70 - 44 - 22;
  function estimateRowHeight(r, segs) {
    const plain = segs ? segsLen(segs) : segsLen(NP.buildNarrativeSegs(r));
    const lines = Math.max(
      Math.max(1, Math.ceil(plain / Math.max(10, EST_CPL))),
      r.pupil ? Math.ceil(String(r.pupil).length / Math.max(2, EST_PUPIL_CPL)) : 1,
      r.consciousness ? Math.ceil(String(r.consciousness).length / 3) : 1
    );
    return Math.max(EST_MIN_H, lines * EST_LINE_H + EST_PAD);
  }
  function paginateEstimated(list) {
    const pages = [];
    let page = [], used = 0;
    list.forEach(function (item) {
      const h = item.type === 'row' ? estimateRowHeight(item.note, item.segs) : EST_SUM_H;
      if (used + h > EST_BODY_BUDGET && page.length) { pages.push(page); page = []; used = 0; }
      page.push(item);
      used += h;
    });
    if (page.length) pages.push(page);
    return pages;
  }

  /* ===== 对外: 生成横板 A4 页面文档数组 ===== */
  NP.notesLandscapePages = function (rows, patient) {
    const list = buildRenderList((rows || []).slice()).filter(function (it) {
      return it.type === 'row' || it.type === 'sum';
    });
    if (!list.length) return [];
    const info = PrintPreview.patientInfo(patient || {}, '护理记录单');
    const headerHtml = (typeof PrintPreview !== 'undefined' && PrintPreview.sheetHeader)
      ? PrintPreview.sheetHeader(info) : '';
    let pages = null;
    const ctx = createMeasureContext(headerHtml);
    if (ctx) {
      try { pages = paginateMeasured(list, ctx); } finally { ctx.dispose(); }
      if (!pages || !pages.length) pages = null;
    }
    if (!pages) pages = paginateEstimated(list);
    return pages.map(function (items, idx) {
      return pageDoc(items, headerHtml, idx + 1, pages.length);
    });
  };

  function pageDoc(items, headerHtml, pageNo, pageCount) {
    let body = '';
    items.forEach(function (item) {
      if (item.type === 'row') body += renderRow(item.note, item.showDate, item.segs, item.cont);
      else if (item.type === 'sum') body += renderSumRow(item.day);
    });
    return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>护理记录单</title>'
        + '<style>' + landscapeCss() + '</style></head><body>'
        + '<div class="page npr-page">'
        + '<div id="patient-header-slot">' + headerHtml + '</div>'
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
