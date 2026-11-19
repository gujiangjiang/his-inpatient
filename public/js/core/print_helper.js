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
        const win = window.open('', '_blank', 'width=860,height=920');
        if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
        win.document.write(`<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>${title}</title>
<style>
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
    .sheet { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 10mm 12mm; box-sizing: border-box; position: relative; }
    .sheet.a5 { width: 148mm; min-height: 210mm; padding: 8mm 10mm; }
    .sheet.half-a4 { width: 210mm; min-height: 148mm; padding: 8mm 12mm; }
    .sheet + .sheet { page-break-before: always; }
    .sheet-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
    .sheet-brand { text-align: center; flex: 1; }
    .sheet-brand .print-hospital { font-size: 16pt; }
    .sheet-docname { font-size: 15pt; font-weight: 700; letter-spacing: 2px; margin-top: 2px; }
    .sheet-barcode { flex-shrink: 0; text-align: right; }
    .sheet-barcode svg { width: 150px; height: auto; }
    .sheet-barcode .barcode-no { font-size: 8.5pt; letter-spacing: 1px; margin-top: 2px; }
    .patient-bar { display: flex; flex-wrap: wrap; gap: 4px 18px; font-size: 10pt; padding: 5px 0; margin-bottom: 8px; }
    .patient-bar span { white-space: nowrap; }
    .patient-bar b { font-weight: 600; }
    .sheet-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    .sheet-table th, .sheet-table td { border: 0.5pt solid #000; padding: 4px 6px; text-align: left; }
    .sheet-table th { background: #f5f5f5; font-weight: 600; }
    .sheet-foot { display: flex; justify-content: space-between; font-size: 10pt; margin-top: 14px; }
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
    }
};
