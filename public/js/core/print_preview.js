// public/js/core/print_preview.js - 通用打印预览与打印运行时
(function () {
    'use strict';
    const PP = (window.PrintPreview = {});
    const PAGE_SELECTOR = '.page, .sheet, .cf-page, .print-page';
    const GAP = 24;
    let current = null;

    /* 只改变纸张外部呈现；纸张尺寸与内部排版始终保持文档原样。 */
    const RUNTIME_CSS = `
:root { --pp-page-gap: ${GAP}px; color-scheme: light; }
@media screen {
  html, body { min-width: 0 !important; min-height: 0 !important; height: auto !important; background: #dfe3e8 !important; }
  html { margin: 0 !important; padding: 0 !important; }
  body { box-sizing: border-box !important; width: 100% !important; margin: 0 !important; padding: var(--pp-page-gap) !important; }
  body > :where(.page, .sheet, .cf-page, .print-page) { background-color: #fff; }
  body > .page, body > .sheet, body > .cf-page, body > .print-page {
    margin: 0 auto var(--pp-page-gap) !important;
    box-shadow: 0 3px 14px rgba(15, 23, 42, .2);
  }
  body > .page:last-of-type, body > .sheet:last-of-type, body > .cf-page:last-of-type, body > .print-page:last-of-type { margin-bottom: 0 !important; }
  html.pp-standalone-root { overflow: auto !important; }
}
@media print {
  html, body { width: auto !important; min-width: 0 !important; height: auto !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
  body > .page, body > .sheet, body > .cf-page, body > .print-page {
    margin: 0 auto !important; box-shadow: none !important;
    break-after: page; page-break-after: always;
  }
  body > .page:last-of-type, body > .sheet:last-of-type, body > .cf-page:last-of-type, body > .print-page:last-of-type {
    break-after: auto; page-break-after: auto;
  }
}`;

    const stripScripts = function (html) {
        return String(html || '').replace(/<script[\s\S]*?<\/script>/gi, '');
    };

    const prepareDocument = function (html) {
        let doc = stripScripts(html).replace(/<style[^>]*id=["']pp-runtime-style["'][^>]*>[\s\S]*?<\/style>/gi, '');
        if (!/<html[\s>]/i.test(doc)) doc = PrintDocument.create('打印文档', doc);
        const style = '<style id="pp-runtime-style">' + RUNTIME_CSS + '</style>';
        if (/<\/head>/i.test(doc)) return doc.replace(/<\/head>/i, style + '</head>');
        return doc.replace(/<html([^>]*)>/i, '<html$1><head>' + style + '</head>');
    };

    const guardEvents = function (doc) {
        ['contextmenu', 'copy', 'cut', 'selectstart'].forEach(function (name) {
            doc.addEventListener(name, function (event) { event.preventDefault(); });
        });
    };

    const closeCurrent = function () {
        if (!current) return;
        if (!current.overlay || !current.overlay.parentNode) {
            if (current.dispose) current.dispose();
            return;
        }
        const close = current.overlay.querySelector('.modal-close');
        if (close) close.click();
        else current.overlay.remove();
    };

    PP.open = function (title, html, opts) {
        const o = opts || {};
        closeCurrent();
        const printableHtml = prepareDocument(html);
        const buttons = [];
        if (o.canPrint !== false) {
            buttons.push({ text: '打印', type: 'primary', onclick: function () {
                PP.print(printableHtml, { beforePrint: o.onPrint }); return false;
            } });
        }
        buttons.push({ text: '关闭', type: 'default' });

        const state = { overlay: null, observers: [], raf: 0, dispose: null };
        const dispose = function () {
            state.observers.forEach(function (observer) { observer.disconnect(); });
            state.observers = [];
            if (state.raf) cancelAnimationFrame(state.raf);
            if (current === state) current = null;
            if (PP._overlay === overlay) PP._overlay = null;
        };
        state.dispose = dispose;
        const overlay = Dom.modal(title || '打印预览',
            '<div class="pp-viewport"><div class="pp-stage"><iframe class="pp-frame" title="打印内容" scrolling="no" sandbox="allow-same-origin"></iframe></div></div>',
            buttons, { onClose: dispose });
        const modal = overlay.querySelector('.modal');
        const viewport = overlay.querySelector('.pp-viewport');
        const stage = overlay.querySelector('.pp-stage');
        const frame = overlay.querySelector('.pp-frame');
        modal.classList.add('modal-print-preview');
        const paperHint = PrintDocument.paper(printableHtml, o);
        modal.style.setProperty('--pp-preview-width', (paperHint.width + GAP * 2) + 'px');
        state.overlay = overlay;
        current = state;
        PP._overlay = overlay;
        PP._html = printableHtml;

        const fit = function () {
            if (current !== state || !frame.contentDocument) return;
            state.raf = 0;
            const doc = frame.contentDocument;
            const pages = Array.from(doc.querySelectorAll(PAGE_SELECTOR));
            const paperWidth = pages.length
                ? pages.reduce(function (width, page) { return Math.max(width, page.offsetWidth); }, 1)
                : 794;
            const naturalWidth = Math.ceil(paperWidth + GAP * 2);
            frame.style.width = naturalWidth + 'px';
            modal.style.setProperty('--pp-preview-width', naturalWidth + 'px');
            const contentHeight = Math.ceil(Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 1));
            const scale = Math.min(1, (viewport.clientWidth || naturalWidth) / naturalWidth);
            frame.style.height = contentHeight + 'px';
            frame.style.transform = 'scale(' + scale + ')';
            stage.style.width = Math.ceil(naturalWidth * scale) + 'px';
            stage.style.height = Math.ceil(contentHeight * scale) + 'px';
        };
        const scheduleFit = function () {
            if (state.raf) cancelAnimationFrame(state.raf);
            state.raf = requestAnimationFrame(fit);
        };
        frame.addEventListener('load', function () {
            const doc = frame.contentDocument;
            if (!doc) return;
            guardEvents(doc);
            fit();
            if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(scheduleFit);
            if (window.ResizeObserver) {
                const contentObserver = new ResizeObserver(scheduleFit);
                contentObserver.observe(doc.body);
                state.observers.push(contentObserver);
            }
        });
        if (window.ResizeObserver) {
            const viewportObserver = new ResizeObserver(scheduleFit);
            viewportObserver.observe(viewport);
            state.observers.push(viewportObserver);
        } else {
            window.addEventListener('resize', scheduleFit, { once: true });
        }
        viewport.addEventListener('contextmenu', function (event) { event.preventDefault(); });
        frame.srcdoc = printableHtml;
        return overlay;
    };

    /* 独立打印页复用相同文档与纸张间距；打印介质下自动去掉灰底、阴影和页间距。 */
    PP.print = function (html, opts) {
        const win = window.open('', '_blank', 'width=900,height=900');
        if (!win) { Dom.toast('浏览器拦截了打印窗口，请允许弹出窗口', 'error'); return null; }
        const options = opts || {};
        let doc = prepareDocument(html);
        const autoPrint = `<script>(function(){window.addEventListener('load',function(){
document.documentElement.classList.add('pp-standalone-root');
var p=document.querySelector('${PAGE_SELECTOR}');
if(p){try{var cw=Math.max(0,outerWidth-innerWidth),ch=Math.max(0,outerHeight-innerHeight);
resizeTo(Math.min(screen.availWidth,p.offsetWidth+${GAP * 2}+cw),Math.min(screen.availHeight,p.offsetHeight+${GAP * 2}+ch));}catch(e){}}
setTimeout(function(){window.print();},180);});})();<\/script>`;
        doc = /<\/body>/i.test(doc) ? doc.replace(/<\/body>/i, autoPrint + '</body>') : doc + autoPrint;
        const render = function () {
            if (win.closed) return;
            win.document.write(doc);
            win.document.close();
        };
        if (typeof options.beforePrint === 'function') {
            Promise.resolve().then(options.beforePrint).then(function (result) {
                if (result === false) { if (!win.closed) win.close(); return; }
                render();
            }).catch(function () { if (!win.closed) win.close(); });
        } else render();
        return win;
    };
})();
