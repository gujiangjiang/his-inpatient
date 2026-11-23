// public/js/core/print/print_document.js - 打印文档构建层（纸张识别、公共样式、页码）
(function () {
    'use strict';
    const PD = (window.PrintDocument = {});
    const MM_TO_PX = 96 / 25.4;
    const makePaper = function (id, widthMm, heightMm, pageSize) {
        return {
            id: id, widthMm: widthMm, heightMm: heightMm,
            width: Math.round(widthMm * MM_TO_PX), height: Math.round(heightMm * MM_TO_PX),
            pageSize: pageSize || (widthMm + 'mm ' + heightMm + 'mm')
        };
    };
    const PAPERS = {
        A4: makePaper('A4', 210, 297, 'A4 portrait'),
        A5: makePaper('A5', 148, 210, 'A5 portrait'),
        HALF_A4: makePaper('HALF_A4', 210, 148),
        WRISTBAND: makePaper('WRISTBAND', 250, 25),
        LAB_LABEL: makePaper('LAB_LABEL', 50, 30),
        MED_LABEL: makePaper('MED_LABEL', 60, 60)
    };

    PD.registerPaper = function (id, widthMm, heightMm) {
        const key = String(id || '').toUpperCase().replace(/[- ]/g, '_');
        const width = Number(widthMm), height = Number(heightMm);
        if (!key || !Number.isFinite(width) || !Number.isFinite(height)
            || width < 10 || height < 10 || width > 500 || height > 500) return null;
        PAPERS[key] = makePaper(key, width, height);
        return PAPERS[key];
    };

    PD.paper = function (bodyHtml, opts) {
        const rawPaper = opts && opts.paper;
        if (rawPaper && typeof rawPaper === 'object') {
            const custom = PD.registerPaper(rawPaper.id || 'CUSTOM', rawPaper.widthMm, rawPaper.heightMm);
            if (custom) return custom;
        }
        const requested = String(rawPaper || '').toUpperCase().replace(/[- ]/g, '_');
        if (PAPERS[requested]) return PAPERS[requested];
        const holder = document.createElement('div');
        holder.innerHTML = String(bodyHtml || '');
        const customPage = holder.querySelector('.print-page[data-paper]');
        if (customPage) {
            const key = String(customPage.getAttribute('data-paper') || '').toUpperCase().replace(/[- ]/g, '_');
            if (PAPERS[key]) return PAPERS[key];
        }
        const sheet = holder.querySelector('.sheet');
        if (sheet && sheet.classList.contains('a5')) return PAPERS.A5;
        if (sheet && sheet.classList.contains('half-a4')) return PAPERS.HALF_A4;
        return PAPERS.A4;
    };

    PD.withPageNumbers = function (bodyHtml) {
        const holder = document.createElement('div');
        holder.innerHTML = String(bodyHtml || '');
        const sheets = holder.querySelectorAll('.sheet');
        sheets.forEach(function (sheet, index) {
            if (sheet.querySelector('.sheet-page-no')) return;
            const pageNo = document.createElement('div');
            pageNo.className = 'sheet-page-no';
            pageNo.textContent = '第 ' + (index + 1) + ' 页 / 共 ' + sheets.length + ' 页';
            sheet.appendChild(pageNo);
        });
        return holder.innerHTML;
    };

    PD.baseCss = function (paper) {
        return `
@page { size: ${paper.pageSize}; margin: 0; }
html, body { margin: 0; padding: 0; }
* { box-sizing: border-box; }
body { font-family: "PingFang SC", "Microsoft YaHei", sans-serif; font-size: 12pt; color: #111; }
.brand-name-lockup { display: inline-grid; grid-template-columns: minmax(0,1fr); width: max-content; max-width: 100%; min-width: 0; }
.brand-name-line { width: 100%; min-width: 0; text-align: justify; text-align-last: justify; text-justify: inter-character; }
.print-brand-lockup { display: grid; margin: 0 auto 8px; }
.print-hospital { font-size: 20pt; font-weight: 700; letter-spacing: 4px; line-height: 1.4; }
.print-hospital2 { font-size: 11pt; font-weight: 400; line-height: 1.35; }
h2.doc-title { text-align: center; font-size: 16pt; margin: 0 0 4px; letter-spacing: 2px; }
.doc-sub { text-align: center; font-size: 9pt; color: #666; margin-bottom: 16px; }
table.doc-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
table.doc-table th, table.doc-table td { border: 1px solid #333; padding: 6px 8px; font-size: 10.5pt; text-align: left; }
table.doc-table th { background: #f2f2f2; }
.doc-meta { display: flex; flex-wrap: wrap; gap: 6px 28px; font-size: 10.5pt; margin: 10px 0; }
.doc-foot { margin-top: 32px; display: flex; justify-content: space-between; font-size: 10.5pt; }
.sheet { width: 210mm; height: 297mm; margin: 0 auto; padding: 12mm 14mm 22mm; position: relative; display: flex; flex-direction: column; overflow: hidden; background: #fff; }
.sheet.a5 { width: 148mm; height: 210mm; padding: 10mm 12mm 20mm; }
.sheet.half-a4 { width: 210mm; height: 148mm; padding: 10mm 14mm 20mm; }
.sheet + .sheet { break-before: page; page-break-before: always; }
.print-page { width: ${paper.widthMm}mm; height: ${paper.heightMm}mm; margin: 0 auto; position: relative; overflow: hidden; background: #fff; }
.print-page + .print-page { break-before: page; page-break-before: always; }
.sheet .sheet-head, .sheet .patient-bar, .sheet .rx-head, .sheet .rx-info,
.sheet .rx-mark, .sheet table.rx-print, .sheet .sheet-table, .sheet .rx-foot,
.sheet .sheet-note, .sheet .sheet-tip { flex-shrink: 0; }
.sheet-foot { margin-top: auto; display: flex; justify-content: space-between; font-size: 10pt; }
.sheet-head { position: relative; display: flex; justify-content: center; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
.sheet-brand { text-align: center; flex: 1; }
/* 抬头字号对齐电子病历 (emr-hos 20pt / emr-docname 16pt), 单据头不再单独缩小 */
.sheet-docname { font-size: 16pt; font-weight: 700; letter-spacing: 3px; line-height: 1.5; margin-top: 2px; }
.sheet-barcode { position: absolute; top: 0; right: 0; text-align: right; }
.sheet-barcode svg { width: 150px; height: auto; }
.sheet-barcode .barcode-no { font-size: 8.5pt; letter-spacing: 1px; margin-top: 2px; }
.patient-bar { display: flex; flex-wrap: wrap; gap: 4px 18px; font-size: 10pt; padding: 5px 0; margin-bottom: 8px; }
.patient-bar span { white-space: nowrap; }
.patient-bar b, .sheet-info-grid .sic b { font-weight: 600; }
.sheet-info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2px 14px; font-size: 10.5pt; padding: 6px 0; margin-bottom: 6px; }
.sheet-info-grid .sic { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sheet-info-grid .sic-diag { grid-column: span 4; white-space: normal; }
.sheet-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
.sheet-table th, .sheet-table td { border: .5pt solid #000; padding: 4px 6px; text-align: left; overflow-wrap: anywhere; }
.sheet-table th { background: #f5f5f5; font-weight: 600; }
.sheet-page-no { position: absolute; left: 0; right: 0; bottom: 40px; text-align: center; font-size: 9pt; color: #333; }
.sheet-note { margin-top: 10px; font-size: 10.5pt; color: #333; }
.sheet-tip { margin-top: 2px; font-size: 9.5pt; color: #666; }
.rx-head { position: relative; text-align: center; padding: 2px 0; }
.rx-brand { display: inline-block; text-align: center; }
.rx-brand .print-hospital { font-size: 20pt; font-weight: 700; letter-spacing: 3px; line-height: 1.3; }
.rx-brand .print-hospital2 { font-size: 11.5pt; letter-spacing: 1px; line-height: 1.3; }
.rx-barcode { position: absolute; top: 0; right: 0; text-align: right; }
.rx-barcode svg { width: 150px; height: auto; }
.rx-title { text-align: center; font-size: 17pt; font-weight: 700; letter-spacing: 4px; margin: 8px 0 6px; }
.rx-info { font-size: 10.5pt; line-height: 1.85; margin-bottom: 4px; }
.rx-info-row { display: flex; flex-wrap: wrap; gap: 3px 16px; }
.rx-info-row span { white-space: nowrap; }
.rx-info-row b { font-weight: 600; }
.rx-info .rx-diag { flex: 1 1 100%; white-space: normal; }
.rx-rule { border-top: 2px solid #111; margin: 6px 0 8px; }
.rx-mark { font-size: 28pt; font-weight: 700; line-height: 1; margin: 2px 0 8px; }
table.rx-print { width: 100%; border-collapse: collapse; font-size: 11.5pt; }
table.rx-print td { border: none; padding: 7px 6px; vertical-align: middle; text-align: left; }
table.rx-print td.rx-name { font-weight: 600; }
table.rx-print td.rx-dose { width: 17%; text-align: center; }
table.rx-print td.rx-route { width: 11%; text-align: center; }
table.rx-print td.rx-freq { width: 15%; text-align: center; }
table.rx-print td.rx-qty { width: 16%; text-align: right; white-space: nowrap; }
.rx-end { text-align: center; letter-spacing: 2px; color: #333; margin: 18px 0 10px; font-size: 11.5pt; }
.rx-foot { margin-top: auto; }
.rx-take { font-size: 10.5pt; margin-bottom: 4px; }
.rx-amount-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: 11.5pt; font-weight: 600; }
.rx-amount-row .rx-doctor { white-space: nowrap; }
.rx-line { border-bottom: 1px solid #111; margin: 3px 0; }
.rx-disp-row, .rx-time-row { display: flex; font-size: 10.5pt; line-height: 1.9; }
.rx-disp-row span { flex: 1; text-align: left; }
.rx-time-row { font-size: 10pt; color: #333; }
.rx-time-row span:first-child { flex: 1; }
.rx-valid { text-align: center; font-size: 10.5pt; margin-top: 6px; }
@media print { .no-print { display: none !important; } .sheet { box-shadow: none; } }
`;
    };

    PD.create = function (title, bodyHtml, opts) {
        const o = opts || {};
        const paper = PD.paper(bodyHtml, o);
        const body = o.pageNumbers === false ? String(bodyHtml || '') : PD.withPageNumbers(bodyHtml);
        const safeTitle = Format.escape(title || '打印文档');
        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + safeTitle + '</title>'
            + '<style>' + PD.baseCss(paper) + String(o.css || '') + '</style></head><body>' + body + '</body></html>';
    };
})();
