// public/js/core/print_helper.js - 打印助手
// print(elementId): 打印页面内元素 (旧用法)
// printHtml(title, bodyHtml): 打开新窗口打印任意 HTML (申请单等, 不破坏当前页面上下文)
const PrintHelper = {
    brandHeaderHtml(extraClass) {
        return HospitalBranding.headerHtml(extraClass || '');
    },

    print(elementId) {
        const el = document.getElementById(elementId);
        if (!el) { window.print(); return; }
        const printContents = el.innerHTML;
        const originalBody = document.body.innerHTML;
        document.body.innerHTML = '<style>@media print {body{font-size:12pt}} .no-print{display:none}</style>' + printContents;
        window.print();
        document.body.innerHTML = originalBody;
        window.location.reload();
    },

    printHtml(title, bodyHtml) {
        // 纸张尺寸：按单据类型注入 @page（A5 / 半张A4横 / A4），
        // 避免 A5 内容被默认 A4 画布拉伸缩放
        const hasA5 = bodyHtml.indexOf('sheet a5') !== -1;
        const hasHalf = bodyHtml.indexOf('sheet half-a4') !== -1;
        const pageSize = hasA5 ? 'A5 portrait'
            : (hasHalf ? '210mm 148mm' : 'A4 portrait');
        // 预览窗口按纸张尺寸：A5 竖→窄高、半A4 横→宽矮、A4→标准
        const winW = hasHalf ? 980 : (hasA5 ? 700 : 900);
        const winH = hasHalf ? 700 : (hasA5 ? 880 : 1000);
        const win = window.open('', '_blank', 'width=' + winW + ',height=' + winH);
        if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
        // 页码注入：给每个 .sheet 底部加「第 x 页 / 共 x 页」（A4/A5/半A4 通用）
        bodyHtml = PrintHelper.withPageNumbers(bodyHtml);
        win.document.write(`<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>${title}</title>
<style>
    @page { size: ${pageSize}; margin: 0; }
    html, body { margin: 0; padding: 0; }
    .sheet, .sheet.a5, .sheet.half-a4 { box-shadow: none; }
    body { font-family: "PingFang SC", "Microsoft YaHei", sans-serif; font-size: 12pt; color: #111; margin: 24px; }
    .brand-name-lockup { display: inline-grid; grid-template-columns: minmax(0,1fr); width: max-content; max-width: 100%; min-width: 0; }
    .brand-name-line { width: 100%; min-width: 0; text-align: justify; text-align-last: justify; text-justify: inter-character; }
    .print-brand-lockup { display: grid; margin: 0 auto 8px; }
    .print-hospital { font-size: 18pt; font-weight: 700; letter-spacing: 2px; line-height: 1.35; }
    .print-hospital2 { font-size: 11pt; font-weight: 400; line-height: 1.35; }
    h2.doc-title { text-align: center; font-size: 16pt; margin: 0 0 4px; letter-spacing: 2px; }
    .doc-sub { text-align: center; font-size: 9pt; color: #666; margin-bottom: 16px; }
    table.doc-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.doc-table th, table.doc-table td { border: 1px solid #333; padding: 6px 8px; font-size: 10.5pt; text-align: left; }
    table.doc-table th { background: #f2f2f2; }
    .doc-meta { display: flex; flex-wrap: wrap; gap: 6px 28px; font-size: 10.5pt; margin: 10px 0; }
    .doc-foot { margin-top: 32px; display: flex; justify-content: space-between; font-size: 10.5pt; }
    /* ===== 门诊式打印公共样式（A4/A5 纸张 + 右上条形码 + 患者信息栏）===== */
    .sheet { width: 210mm; height: 297mm; margin: 0 auto; padding: 12mm 14mm 16mm; box-sizing: border-box; position: relative; display: flex; flex-direction: column; }
    .sheet.a5 { width: 148mm; height: 210mm; padding: 10mm 11mm 13mm; }
    .sheet.half-a4 { width: 210mm; height: 148mm; padding: 10mm 14mm 14mm; }
    .sheet + .sheet { page-break-before: always; }
    .sheet .sheet-head, .sheet .patient-bar, .sheet .rx-mark, .sheet table.rx-print, .sheet .sheet-table { flex-shrink: 0; }
    .sheet .sheet-note, .sheet .sheet-tip { flex-shrink: 0; }
    .sheet-foot, .rx-note.rx-sign-row { margin-top: auto; }
    .rx-note.rx-sign-row { margin-top: auto !important; }
    .rx-line { margin-top: 0; }
    .rx-note.rx-disp-row { margin-top: 0; }
    .sheet-head { position: relative; display: flex; justify-content: center; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
    .sheet-brand { text-align: center; flex: 1; }
    .sheet-brand .print-hospital { font-size: 16pt; }
    .sheet-docname { font-size: 15pt; font-weight: 700; letter-spacing: 2px; margin-top: 2px; }
    .sheet-barcode { position: absolute; top: 0; right: 0; text-align: right; }
    .sheet-barcode svg { width: 150px; height: auto; }
    .sheet-barcode .barcode-no { font-size: 8.5pt; letter-spacing: 1px; margin-top: 2px; }
    .patient-bar { display: flex; flex-wrap: wrap; gap: 4px 18px; font-size: 10pt; padding: 5px 0; margin-bottom: 8px; }
    /* 门诊式患者信息栏：隐形 grid（报告单用，姓名/性别/年龄/出生日期 + ID/住院号/科室/诊断） */
    .sheet-info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 2px 14px; font-size: 10.5pt; padding: 6px 0; margin-bottom: 6px; }
    .sheet-info-grid .sic { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sheet-info-grid .sic b { font-weight: 600; }
    .sheet-info-grid .sic-diag { grid-column: span 4; white-space: normal; }
    @media (max-width: 720px) { .sheet-info-grid { grid-template-columns: 1fr 1fr; } }
    .patient-bar span { white-space: nowrap; }
    .patient-bar b { font-weight: 600; }
    .sheet-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    .sheet-table th, .sheet-table td { border: 0.5pt solid #000; padding: 4px 6px; text-align: left; }
    .sheet-table th { background: #f5f5f5; font-weight: 600; }
    .sheet-foot { display: flex; justify-content: space-between; font-size: 10pt; }
    .sheet-page-no { position: absolute; left: 0; right: 0; bottom: 9px; text-align: center; font-size: 9pt; color: #333; }
    .sheet-note { margin-top: 10px; font-size: 10.5pt; color: #333; }
    .sheet-tip { margin-top: 2px; font-size: 9.5pt; color: #666; }
    /* ===== 门诊标准处方样式（A5 无边框，参照标准医院处方笺） ===== */
    .rx-mark { font-size: 26pt; font-weight: 700; line-height: 1; margin: 6px 0 8px; color: #111; }
    table.rx-print { width: 100%; border-collapse: collapse; font-size: 11pt; }
    table.rx-print td { border: none; padding: 4px 6px; vertical-align: middle; text-align: left; }
    table.rx-print td.rx-name { font-weight: 600; }
    table.rx-print td.rx-qty { text-align: right; white-space: nowrap; }
    .rx-end { text-align: center; letter-spacing: 2px; color: #333; margin: 10px 0 4px; font-size: 11pt; }
    .rx-note { margin-top: 8px; font-size: 10.5pt; color: #333; }
    .rx-note.rx-take { margin-top: 0; }
    .rx-sign-row .rx-take { flex: 1; text-align: left; font-weight: normal; }
    .rx-sign-row .rx-sign-right { display: inline-flex; gap: 12px; align-items: baseline; white-space: nowrap; }
    .rx-sign-row .rx-sign-right .rx-amount { font-weight: 600; }
    .rx-sign-row { display: flex; align-items: center; font-weight: 600; margin-top: 10px; }
    .rx-sign-row .rx-amount { flex: 1; text-align: left; }
    .rx-disp-row { display: flex; font-size: 10.5pt; line-height: 1.8; margin-top: 4px; margin-bottom: 4px; }
    .rx-disp-row span { flex: 1; text-align: left; }
    .rx-center { text-align: center; }
    .rx-line { border-bottom: 1px solid #111; padding-bottom: 6px; margin-bottom: 6px; }
    @media print {
        body { margin: 0; }
        .sheet { box-shadow: none; }
        .no-print { display: none; }
    }
</style></head><body>${bodyHtml}
<script>window.onload = function () { window.print(); };<\/script>
</body></html>`);
        win.document.close();
    },

    /* 打印完整 HTML 文档 (与预览 iframe 使用同一份文档, 保证一模一样) */
    printDocument(docHtml) {
        const win = window.open('', '_blank', 'width=900,height=1000');
        if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
        win.document.write(docHtml);
        win.document.close();
    },

    /* 给打印 bodyHtml 的每个 .sheet 注入页码「第 x 页 / 共 x 页」（底部居中） */
    withPageNumbers(bodyHtml) {
        const tmp = document.createElement('div');
        tmp.innerHTML = bodyHtml;
        const sheets = tmp.querySelectorAll('.sheet');
        const total = sheets.length;
        sheets.forEach((s, i) => {
            if (s.querySelector('.sheet-page-no')) return;
            const pg = document.createElement('div');
            pg.className = 'sheet-page-no';
            pg.textContent = '第 ' + (i + 1) + ' 页 / 共 ' + total + ' 页';
            s.appendChild(pg);
        });
        return tmp.innerHTML;
    },

    /* 前端 Code 128 条形码（SVG，供打印模板右上角用） */
    barcodeSvg(text, height = 36, scale = 1, withText = true) {
        const P = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232'
        ];
        text = String(text == null ? '' : text);
        if (!text) return '';
        const seq = [104]; let check = 104; let n = 0;
        for (let i = 0; i < text.length; i++) {
            const v = text.charCodeAt(i) - 32;
            if (v < 0 || v > 94) continue;
            seq.push(v); check += v * (++n);
        }
        if (!n) return '';
        seq.push(check % 103);
        const stop = '2331112';
        const total = 20 + seq.length * 11 + 13;
        const w = total * scale;
        let x = 10 * scale, isBar = true, rects = '';
        const draw = (pat) => {
            for (let k = 0; k < pat.length; k++) {
                const mw = parseInt(pat[k], 10) * scale;
                if (isBar) rects += '<rect x="' + x + '" y="0" width="' + mw + '" height="' + height + '"/>';
                x += mw; isBar = !isBar;
            }
        };
        seq.forEach(v => draw(P[v] || '212222'));
        draw(stop);
        let textEl = '', h = height;
        if (withText) {
            const barLeft = 10 * scale;
            const barW = x - barLeft;
            if (barW > 0) {
                const textH = Math.max(14, Math.round(height * 0.4));
                textEl = '<text x="' + barLeft + '" y="' + (height + textH) + '" textLength="' + barW + '" lengthAdjust="spacing"' +
                    ' font-size="' + textH + '" text-anchor="start" fill="#111" font-family="Menlo, Consolas, Courier New, monospace">' +
                    Format.escape(text) + '</text>';
                h = height + textH + 6;
            }
        }
        return '<svg viewBox="0 0 ' + w + ' ' + h + '" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" role="img" aria-label="' + Format.escape(text) + '">' +
            '<rect width="' + w + '" height="' + h + '" fill="#ffffff"/>' + rects + textEl + '</svg>';
    }
};
