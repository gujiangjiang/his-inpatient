// public/js/core/print_helper.js - 打印助手
// print(elementId): 打印页面内元素 (旧用法)
// printHtml(title, bodyHtml): 打开新窗口打印任意 HTML (申请单等, 不破坏当前页面上下文)
const PrintHelper = {
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
    h2.doc-title { text-align: center; font-size: 16pt; margin: 0 0 4px; letter-spacing: 2px; }
    .doc-sub { text-align: center; font-size: 9pt; color: #666; margin-bottom: 16px; }
    table.doc-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.doc-table th, table.doc-table td { border: 1px solid #333; padding: 6px 8px; font-size: 10.5pt; text-align: left; }
    table.doc-table th { background: #f2f2f2; }
    .doc-meta { display: flex; flex-wrap: wrap; gap: 6px 28px; font-size: 10.5pt; margin: 10px 0; }
    .doc-foot { margin-top: 32px; display: flex; justify-content: space-between; font-size: 10.5pt; }
    @media print { body { margin: 0; } }
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
