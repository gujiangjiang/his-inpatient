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
      + (header.hospital ? '<div class="emr-hos">' + esc(header.hospital) + '</div>' : '')
      + (header.doc_name ? '<div class="emr-docname">' + esc(header.doc_name) + '</div>' : '')
      + (fields ? '<div class="emr-infobar">' + fields + '</div>' : '')
      + '</div>'
      + '<div class="pg-body">' + (bodyHTML || '') + '</div>'
      + '<div class="pg-foot"><span class="emr-fp-left">' + esc(header.hospital || '') + '</span>'
      + '<span class="emr-fp-page">第 ' + (idx + 1) + ' 页 / 共 ' + total + ' 页</span></div>'
      + '</div>';
  };

  /** 组装完整打印文档 HTML (同时用作 content_html 快照) */
  P.buildHTML = (doc, patientInfo) => {
    const info = patientInfo || {};
    const res = L.paginate(doc, info);
    const d = res.dim;
    const pages = res.pages.length ? res.pages : ['<div class="p"><div class="pc"><br></div></div>'];
    const style = [
      '@page { size: A4; margin: 0; }',
      'html, body { margin: 0; padding: 0; }',
      '.page { position: relative; width: ' + d.w + 'px; height: ' + d.h + 'px; overflow: hidden; page-break-after: always; }',
      '.page:last-child { page-break-after: auto; }',
      '.pg-head { position: absolute; left: ' + d.ml + 'px; right: ' + d.mr + 'px; top: ' + d.mt + 'px; text-align: center; }',
      '.emr-hos { font-size: 20pt; font-weight: bold; letter-spacing: 4px; line-height: 1.4; }',
      '.emr-docname { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.5; }',
      '.emr-infobar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2px 20px; margin-top: 8px; padding: 6px 4px 6px; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; font-size: 11pt; }',
      '.pg-body { position: absolute; left: ' + d.bodyLeft + 'px; top: ' + d.bodyTop + 'px; width: ' + d.bodyW + 'px; height: ' + d.bodyH + 'px; overflow: hidden; font-family: ' + (doc.page.font || 'serif') + '; font-size: ' + (doc.page.sz || 12) + 'pt; line-height: ' + (doc.page.line || 1.5) + '; white-space: pre-wrap; overflow-wrap: break-word; }',
      '.pg-body .p { margin: 0; }',
      '.pg-body .emr-slot { display: none !important; } /* 打印不显示填写占位符 */',
      '.pg-foot { position: absolute; left: ' + d.ml + 'px; right: ' + d.mr + 'px; top: ' + (d.h - d.mb - d.footerH) + 'px; height: ' + d.footerH + 'px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt; color: #000; }',
      '.emr-fx { font-weight: bold; }',
      '.emr-tbl { border-collapse: collapse; width: 100%; margin: 6px 0; }',
      '.emr-tbl td { border: 0.5pt solid #000; padding: 4px 6px; vertical-align: top; }',
    ].join('\n');

    const html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + esc(doc.title || '病历') + '</title>'
      + '<style>' + style + '</style></head><body>'
      + pages.map((b, i) => pageHTML(d, doc.header, b, i, pages.length, info)).join('')
      + '<script>window.onload = function(){ setTimeout(function(){ window.print(); }, 120); };<\/script>'
      + '</body></html>';
    return html;
  };

  /** 打开打印窗口并自动打印 */
  P.print = (doc, patientInfo) => {
    const win = window.open('', '_blank', 'width=900,height=1000');
    if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return null; }
    win.document.write(P.buildHTML(doc, patientInfo));
    win.document.close();
    return win;
  };
})();