// public/js/case_front_page/home_print.js - 病案首页打印: 版式 CSS / 预览 / 打印 (预览与打印同一份文档)
Object.assign(CaseHome, {
    /* 打印文档 (预览 iframe 与打印窗口使用同一份, 保证一模一样) */
    documentHtml(withScript) {
        const script = withScript ? '<script>window.addEventListener("load", function () { window.print(); });<\/script>' : '';
        return `<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>住院病案首页</title>
<style>${this.printCss()}</style></head><body>${this.buildPage1()}${this.buildPage2()}${script}</body></html>`;
    },

    print() {
        PrintHelper.printDocument(this.documentHtml(true));
    },

    /* 打印预览: iframe 内渲染与打印完全相同的文档 */
    preview() {
        const overlay = Dom.modal('住院病案首页 · 打印预览', '<iframe class="cf-preview-frame" id="cf-preview-frame"></iframe>', [
            { text: '打印', type: 'primary', onclick: () => this.print() },
            { text: '关闭', type: 'default' },
        ]);
        overlay.querySelector('.modal').classList.add('modal-cf-preview');
        const frame = overlay.querySelector('#cf-preview-frame');
        frame.srcdoc = this.documentHtml(false);
    },

    /* 值: 纯文本; 空值为空串 */
    h(k) {
        const x = this.data[k];
        return (x === '' || x === undefined || x === null) ? '' : Format.escape(String(x));
    },

    /* 值: 空值显示 — */
    vd(k) {
        const x = this.data[k];
        return (x === '' || x === undefined || x === null) ? '—' : Format.escape(String(x));
    },

    /* 勾选/编码方框: 有值显示值, 无值空框 */
    bx(k) {
        const x = this.data[k];
        return '<b class="cf-bx">' + ((x === '' || x === undefined || x === null) ? '&nbsp;' : Format.escape(String(x))) + '</b>';
    },

    fn(k, fallback) {
        const x = this.data[k];
        return (x === '' || x === undefined || x === null) ? (fallback === undefined ? '—' : fallback) : Format.escape(String(x));
    },

    /* 中文日期时间: 2026-10-07 08:31 → 2026年10月7日8时31分 */
    fmtCn(k) {
        const x = this.data[k];
        if (!x) return '—';
        const m = String(x).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):?(\d{2})?/);
        if (!m) return Format.escape(String(x));
        return (+m[1]) + '年' + (+m[2]) + '月' + (+m[3]) + '日' + (+m[4]) + '时' + (m[5] ? (+m[5]) + '分' : '');
    },

    printCss() {
        return `
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0 !important; padding: 0 !important; }
body { background: #e9edf2; }
.cf-page {
    width: 210mm; height: 296mm; padding: 5.5mm 7mm 5mm; margin: 0 auto 14px;
    background: #fff; overflow: hidden; position: relative;
    font-family: "SimSun", "宋体", "Songti SC", "STSong", serif;
    font-size: 8.8pt; line-height: 1.32; color: #000;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.cf-page + .cf-page { page-break-before: always; }
@media screen { body { padding: 12px 0; } .cf-page { box-shadow: 0 2px 14px rgba(0,0,0,.22); } }
@media print { body { background: #fff; padding: 0 !important; } .cf-page { margin: 0 auto; box-shadow: none; } }
.cf-v { color: #1a1a1a; font-weight: 600; }
.cf-num { color: #1a1a1a; }
.cf-fill-line {
    display: inline-block; min-width: 32mm; border-bottom: .7pt solid #000;
    text-align: center; padding: 0 1mm; line-height: 1.34; font-weight: 600; color: #1a1a1a;
}
.cf-bx {
    display: inline-block; min-width: 4.1mm; height: 4.3mm; line-height: 4.1mm;
    text-align: center; border: .8pt solid #000; padding: 0 .4mm; margin: 0 .5mm;
    font-weight: 600; font-size: 8.8pt; vertical-align: -0.3mm;
}
/* ===== 页头 ===== */
.cf-head { margin-bottom: 1.4mm; }
.cf-head-top { display: flex; justify-content: space-between; align-items: flex-end; font-size: 10.5pt; font-weight: 700; padding: 0 1mm; }
.cf-org .cf-fill-line { min-width: 50mm; }
.cf-orgcode { font-weight: 700; }
.cf-orgcode .cf-fill-line { min-width: 38mm; }
.cf-title-row { position: relative; text-align: center; margin: 1.2mm 0 1.6mm; min-height: 9mm; }
.cf-title { font-size: 17pt; font-weight: 700; letter-spacing: 3.2mm; text-indent: 3.2mm; line-height: 1.25; }
.cf-pay { position: absolute; left: 1mm; top: 50%; transform: translateY(-50%); font-size: 10pt; font-weight: 400; }
.cf-head-id { display: flex; justify-content: space-between; align-items: flex-end; font-size: 10pt; padding: 0 1mm; }
.cf-head-id .cf-fill-line { min-width: 28mm; }
/* ===== 首页(一) 信息框: 表格 + 隐藏内线, 仅外框与分组分隔线 ===== */
table.cf1 { width: 100%; border-collapse: collapse; table-layout: fixed; border: 1pt solid #000; }
table.cf1 > tbody > tr > td { border: none; padding: .55mm 1.4mm; }
table.cf1 tr.cf-line > td { height: 7.15mm; }
table.cf1 tr.cf-topline > td { border-top: .8pt solid #000; }
table.cf1 tr.cf-diagrow > td { padding: 0; }
table.cf-diag { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.cf-diag th, table.cf-diag td { border: .8pt solid #000; padding: .3mm 1.4mm; height: 7.35mm; vertical-align: middle; }
table.cf-diag th { height: 7.15mm; font-weight: 400; text-align: center; }
.cf-note { font-size: 8.2pt; }
.cf-patho .cf-prow { display: flex; justify-content: space-between; }
.cf-patho .cf-prow > span { width: 50%; }
/* ===== 首页(二) ===== */
table.cf2 { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.cf2 > tbody > tr > td { border: .8pt solid #000; padding: .55mm 1.4mm; height: 7.3mm; vertical-align: middle; }
table.cf2 td.cf-nest { padding: 0; }
table.cf2 td.cf-note-r { font-size: 8.4pt; line-height: 1.45; }
table.cf-op { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.cf-op th, table.cf-op td { border: .8pt solid #000; padding: .3mm .8mm; height: 7.2mm; text-align: center; vertical-align: middle; font-weight: 400; }
table.cf-op th { font-size: 8.6pt; line-height: 1.25; }
.cf-feebox { padding: .8mm 1.4mm !important; }
.cf-fee-line { line-height: 5.85mm; white-space: nowrap; }
.cf-fee-line .cf-indent { display: inline-block; width: 7mm; }
.cf-foot-note { font-size: 9pt; line-height: 1.5; margin-top: 1mm; padding: 0 1mm; }
.cf-tc { text-align: center; }
/* ===== 预览 ===== */
.modal-cf-preview { max-width: 880px; width: 96%; }
.cf-preview-frame { width: 100%; height: calc(100vh - 230px); min-height: 460px; border: 0; background: #e9edf2; }
`;
    },
});
