// public/js/emr_editor/emr_doc_print.js - 打印文档组装 (页眉/信息栏/页脚/自动分页打印)
// 与编辑画布共用 EmrLayout.paginate 结果, 保证预览即打印;
// buildHTML 生成的完整 HTML 同时作为 content_html 快照随 doc_json 一起保存
(function () {
  'use strict';
  const L = window.EmrLayout;
  const P = (window.EmrPrint = {});
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  /** 单页 HTML: 抬头(医院名+文书名+信息栏) + 正文区 + 页脚(页码) */
  const pageHTML = (d, header, bodyHTML, idx, total, patientInfo) => {
    const fields = (header.info_fields || []).map((f) => {
      const [label, key] = f;
      return '<span class="emr-hf"><b>' + esc(label) + '</b><span class="emr-hf-val">' + esc(patientInfo[key] || '') + '</span></span>';
    }).join('');
    return '<div class="page">'
      + '<div class="pg-head">'
      + (header.hospital ? '<div class="brand-name-lockup emr-brand-lockup"><div class="brand-name-line emr-hos">' + esc(header.hospital) + '</div>'
        + (header.hospital2 ? '<div class="brand-name-line emr-hos2">' + esc(header.hospital2) + '</div>' : '') + '</div>' : '')
      + (header.doc_name ? '<div class="emr-docname">' + esc(header.doc_name) + '</div>' : '')
      + (fields ? '<div class="emr-infobar">' + fields + '</div>' : '')
      + '</div>'
      + '<div class="pg-body">' + (bodyHTML || '') + '</div>'
      + '<div class="pg-foot"><span class="emr-fp-left">' + esc(header.hospital || '') + '</span>'
      + '<span class="emr-fp-page">第 ' + (idx + 1) + ' 页 / 共 ' + total + ' 页</span></div>'
      + '</div>';
  };

  /** 组装完整打印文档 HTML (同时用作 content_html 快照)
   *  opts: { fromPage, toPage, contentMode: 'full'|'body' } 页数/内容选择 */
  P.buildHTML = (doc, patientInfo, opts) => {
    const info = patientInfo || {};
    const o = opts || {};
    const res = L.paginate(doc, info);
    const d = res.dim;
    let pages = res.pages.length ? res.pages : ['<div class="p"><div class="pc"><br></div></div>'];
    // 页码范围：fromPage 从 1 起（1 基）
    const fromPage = Math.max(1, parseInt(o.fromPage, 10) || 1);
    const toPage = Math.min(pages.length, parseInt(o.toPage, 10) || pages.length);
    pages = pages.slice(fromPage - 1, toPage);
    const totalPage = pages.length;
    const bodyOnly = o.contentMode === 'body';   // 仅正文：隐藏页头（病历头）
    if (bodyOnly) doc = Object.assign({}, doc, { header: null });
    const style = [
      '@page { size: A4; margin: 0; }',
      'html, body { margin: 0; padding: 0; }',
      '* { box-sizing: border-box; } /* 与编辑器 reset 对齐: 否则 td padding 撑宽表格 → 信息表不居中/宽度失真 */',
      '.page { position: relative; width: ' + d.w + 'px; height: ' + d.h + 'px; overflow: hidden; page-break-after: always; }',
      '.page:last-child { page-break-after: auto; }',
      '.pg-head { position: absolute; left: ' + d.ml + 'px; right: ' + d.mr + 'px; top: ' + d.mt + 'px; text-align: center; }',
      '.emr-hos { font-size: 20pt; font-weight: bold; letter-spacing: 4px; line-height: 1.4; }',
      '.brand-name-lockup { display:inline-grid; grid-template-columns:minmax(0,1fr); width:max-content; max-width:100%; min-width:0; }',
      '.brand-name-line { width:100%; min-width:0; text-align:justify; text-align-last:justify; text-justify:inter-character; }',
      '.emr-brand-lockup { display:grid; margin:0 auto; }',
      '.emr-hos2 { font-size:12pt; font-weight:normal; line-height:1.35; }',
      '.emr-docname { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.5; }',
      '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 20px; margin-top: 8px; padding: 6px 4px 6px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 11pt; }',
      '.pg-body { position: absolute; left: ' + d.bodyLeft + 'px; top: ' + d.bodyTop + 'px; width: ' + d.bodyW + 'px; height: ' + d.bodyH + 'px; overflow: hidden; font-family: ' + (doc.page.font || 'serif') + '; font-size: ' + (doc.page.sz || 12) + 'pt; line-height: ' + (doc.page.line || 1.5) + '; white-space: pre-wrap; overflow-wrap: break-word; }',
      '.pg-body .p { margin: 0; }',
      '.pg-body .emr-slot, .pg-body .emr-ph { display: none !important; } /* 打印不显示填写槽/占位符 */',
      '.pg-body .emr-vital-slot.emr-slot-empty { display: inline-block !important; width: 3em; border-bottom: 1px solid #777; }',
      '.pg-body .emr-tbl-plain .emr-fx { font-weight: normal; } /* 信息表标签保持常规字重 (与预览一致) */',
      '.pg-body .emr-val { border-bottom: none !important; } /* 打印不显示可编辑下划线 */',
      '.pg-body .emr-ref { border-bottom: none !important; }',
      '.pg-foot { position: absolute; left: ' + d.ml + 'px; right: ' + d.mr + 'px; top: ' + (d.h - d.mb - d.footerH) + 'px; height: ' + d.footerH + 'px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt; color: #000; }',
      '.pg-foot .emr-fp-page { position: absolute; left: 0; right: 0; bottom: 0; text-align: center; }',
      '.emr-fx { font-weight: bold; }',
      '.emr-flx-center > .emr-fx:not(.emr-vital-unit) { margin-left: 24px; }',
      '.emr-flx-center > .emr-fx:first-child { margin-left: 0; }',
      '.emr-vital-unit { font-weight: normal; margin-left: 4px; }',
      '.emr-tbl { border-collapse: collapse; margin: 6px 0; table-layout: fixed; }',
      '.emr-tbl td { border: 0.5pt solid #000; padding: 4px 6px; vertical-align: top; }',
      '.emr-tbl-bold td { border-width: 1.5pt; }',
      '.emr-tbl-none td { border: none !important; }',
      '.emr-tbl-plain { margin: 2px 0 6px; table-layout: fixed; }',
      '.emr-tbl-plain td { border: none !important; padding: 1px 6px 1px 0; vertical-align: top; white-space: nowrap; }',
      '.emr-tbl-plain .emr-ph { display: none !important; }',
    ].join('\n');

    const html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + esc(doc.title || '病历') + '</title>'
      + '<style>' + style + '</style></head><body>'
      + pages.map((b, i) => pageHTML(d, doc.header || {}, b, i, pages.length, info)).join('')
      + '<script>window.onload = function(){ setTimeout(function(){ window.print(); }, 120); };<\/script>'
      + '</body></html>';
    return html;
  };

  /** 打开打印窗口并自动打印（opts: 页码范围/内容选择透传 buildHTML） */
  P.print = (doc, patientInfo, opts) => {
    const win = window.open('', '_blank', 'width=994,height=1323'); // A4 210×297mm ≈ 794×1123px + 窗口装饰/边距
    if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return null; }
    win.document.write(P.buildHTML(doc, patientInfo, opts));
    win.document.close();
    return win;
  };

  /**
   * 批量打印组装（病程记录等）：多文书连续排列，自动分页。
   * 页头以第一文书的 headerHTML 为准（用户约定：批量打印文书头保持一致，以第一个为准）。
   * 每文书：第一页正文顶部插小标题（YYYY-MM-DD HH:mm 文书名），末页正文底部右下签名。
   * 全局页码连续；opts.signHtml 为该文书签名串（右下角）。
   */
  P.buildBatch = (items, patientInfo) => {
    const info = patientInfo || {};
    const all = items.filter(x => x && x.doc);
    if (!all.length) return '';
    // 第一文书分页结果决定页面尺寸与页头
    const first = L.paginate(all[0].doc, info);
    const d = first.dim;
    const headerHTML = first.headerHTML;
    const firstHeader = all[0].doc.header || {};
    const pages = [];
    let pageIdx = 0;
    let docIdx = 0;
    all.forEach(it => {
      const doc = it.doc;
      const res = L.paginate(doc, info);
      const docPages = res.pages.length ? res.pages : ['<div class="p"><div class="pc"><br></div></div>'];
      const stamp = (it.stamp || doc.updated_at || doc.created_at || '').toString();
      const title = doc.title || '文书';
      const timeStr = stamp.replace(/[-:T]/g, m => m === 'T' ? ' ' : m).slice(0, 16);
      docPages.forEach((body, pi) => {
        // 第 1 页正文顶部加小标题：时间靠左 + 文书名居中
        let b = body;
        if (pi === 0) b = '<div class="emr-batch-title"><span class="emr-bt-cell emr-bt-time">' + esc(timeStr) + '</span><span class="emr-bt-cell emr-bt-name">' + esc(title) + '</span><span class="emr-bt-cell emr-bt-void"></span></div>' + b;
        if (pi === docPages.length - 1 && it.signHtml) {
          b = b + '<div class="emr-batch-sign">' + it.signHtml + '</div>';
        }
        pages.push(pageHTML(d, firstHeader, b, pageIdx, pageIdx + 1, info));
        pageIdx++;
      });
      docIdx++;
    });
    const style = [
      '@page { size: A4; margin: 0; }',
      'html, body { margin: 0; padding: 0; }',
      '* { box-sizing: border-box; }',
      '.page { position: relative; width: ' + d.w + 'px; height: ' + d.h + 'px; overflow: hidden; page-break-after: always; }',
      '.page:last-child { page-break-after: auto; }',
      '.pg-head { position: absolute; left: ' + d.ml + 'px; right: ' + d.mr + 'px; top: ' + d.mt + 'px; text-align: center; }',
      '.emr-hos { font-size: 20pt; font-weight: bold; letter-spacing: 4px; line-height: 1.4; }',
      '.brand-name-lockup { display:inline-grid; grid-template-columns:minmax(0,1fr); width:max-content; max-width:100%; min-width:0; }',
      '.brand-name-line { width:100%; min-width:0; text-align:justify; text-align-last:justify; text-justify:inter-character; }',
      '.emr-brand-lockup { display:grid; margin:0 auto; }',
      '.emr-hos2 { font-size:12pt; font-weight:normal; line-height:1.35; }',
      '.emr-docname { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.5; }',
      '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 20px; margin-top: 8px; padding: 6px 4px 6px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 11pt; }',
      '.pg-body { position: absolute; left: ' + d.bodyLeft + 'px; top: ' + d.bodyTop + 'px; width: ' + d.bodyW + 'px; height: ' + d.bodyH + 'px; overflow: hidden; font-family: ' + (all[0].doc.page.font || 'serif') + '; font-size: ' + (all[0].doc.page.sz || 12) + 'pt; line-height: ' + (all[0].doc.page.line || 1.5) + '; white-space: pre-wrap; overflow-wrap: break-word; }',
      '.pg-body .p { margin: 0; }',
      '.pg-body .emr-slot, .pg-body .emr-ph { display: none !important; }',
      '.pg-body .emr-tbl-plain .emr-fx { font-weight: normal; }',
      '.pg-body .emr-val { border-bottom: none !important; }',
      '.pg-body .emr-ref { border-bottom: none !important; }',
      '.emr-batch-title { display: flex; font-size: 12pt; margin: 0 0 6px; padding-bottom: 4px; border-bottom: 1px solid #000; }',
      '.emr-batch-title .emr-bt-cell { flex: 1 1 0; }',
      '.emr-batch-title .emr-bt-time { font-weight: normal; white-space: nowrap; text-align: left; }',
      '.emr-batch-title .emr-bt-name { text-align: center; font-weight: bold; }',
      '.emr-batch-sign { text-align: right; margin-top: 20px; font-size: 11pt; }',
      '.pg-foot { position: absolute; left: ' + d.ml + 'px; right: ' + d.mr + 'px; top: ' + (d.h - d.mb - d.footerH) + 'px; height: ' + d.footerH + 'px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt; color: #000; }',
      '.emr-fx { font-weight: bold; }',
      '.emr-tbl { border-collapse: collapse; margin: 6px 0; table-layout: fixed; }',
      '.emr-tbl td { border: 0.5pt solid #000; padding: 4px 6px; vertical-align: top; }',
      '.emr-tbl-plain { margin: 2px 0 6px; table-layout: fixed; }',
      '.emr-tbl-plain td { border: none !important; padding: 1px 6px 1px 0; vertical-align: top; white-space: nowrap; }',
    ].join('\n');
    const html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + esc('病历批量打印') + '</title>'
      + '<style>' + style + '</style></head><body>'
      + pages.map(p => p).join('')
      + '<script>window.onload = function(){ setTimeout(function(){ window.print(); }, 120); };<\/script>'
      + '</body></html>';
    return html;
  };

  /** 批量打印开窗 */
  P.printBatch = (items, patientInfo) => {
    const win = window.open('', '_blank', 'width=994,height=1323'); // A4 210×297mm ≈ 794×1123px + 窗口装饰/边距
    if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return null; }
    win.document.write(P.buildBatch(items, patientInfo));
    win.document.close();
    return win;
  };
})();
