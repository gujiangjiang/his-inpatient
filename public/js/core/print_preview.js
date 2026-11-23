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

    /* 防御纵深: 预览文档不执行任何脚本。预览 iframe 沙箱刻意不含 allow-scripts,
       历史快照可能残留的内联事件 (on*) 或未配对脚本节点一旦在沙箱内尝试执行,
       浏览器会记录 "Blocked script execution in 'about:srcdoc'..." 报错;
       在父文档侧统一摘除, 既阻止执行, 也消除控制台噪音。 */
    const sanitizeFrame = function (doc) {
        try {
            doc.querySelectorAll('script').forEach(function (el) { el.remove(); });
            doc.querySelectorAll('*').forEach(function (el) {
                Array.from(el.attributes).forEach(function (attr) {
                    if (/^on/i.test(attr.name)) el.removeAttribute(attr.name);
                });
            });
        } catch (e) { /* 文档替换间隙访问失败时忽略, 等待下一次 load 再清理 */ }
    };

    /* 统一打印出口: 对承载打印文档的 iframe 直接调起系统打印组件。
       预览内打印复用可见 iframe (所见即所得), 独立打印用隐藏承载 iframe,
       均不再新开窗口二次预览。 */
    const printContentWindow = function (frame) {
        const doc = frame && frame.contentDocument;
        if (!frame || !frame.contentWindow || !doc || !doc.body) return false;
        frame.contentWindow.focus(); /* Safari 需先聚焦 iframe 窗口, 否则可能打印父页面 */
        frame.contentWindow.print();
        return true;
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
                /* 预览已是所见即所得: 直接对预览 iframe 调起系统打印组件, 不再新开窗口二次预览 */
                const doPrint = function () {
                    if (!printContentWindow(overlay.querySelector('.pp-frame'))) {
                        Dom.toast('打印内容未就绪，请稍候重试', 'error');
                    }
                };
                if (typeof o.onPrint === 'function') {
                    /* 打印前置钩子 (如条码打印留痕): 与原 beforePrint 语义一致, 返回 false 或失败则放弃打印 */
                    Promise.resolve().then(o.onPrint).then(function (result) {
                        if (result !== false) doPrint();
                    }).catch(function () { /* 前置失败不打印 */ });
                } else doPrint();
                return false;
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
            /* allow-same-origin: 父页面读取文档测量排版; allow-modals: 浏览器把 print()
               归入沙箱 modals 门控, 缺失会导致"打印"被静默忽略 (Ignored call to 'print()')。
               内容经 stripScripts + sanitizeFrame 清理, 无脚本不会触发任何弹窗。 */
            '<div class="pp-viewport"><div class="pp-stage"><iframe class="pp-frame" title="打印内容" scrolling="no" sandbox="allow-same-origin allow-modals"></iframe></div></div>',
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
            state.raf = 0;
            if (current !== state) return;
            const doc = frame.contentDocument;
            /* about:blank → about:srcdoc 文档替换的解析间隙 body 可能尚未生成,
               此时跳过本次布局, 由后续 load / ResizeObserver / fonts.ready 再次调度,
               否则 doc.body.scrollHeight 间歇性抛 TypeError (预览偶发报错的根因之一) */
            if (!doc || !doc.body || !doc.documentElement) return;
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
            if (!doc || !doc.body) return;
            guardEvents(doc);
            sanitizeFrame(doc);
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

    /* 独立打印 API: 隐藏承载 iframe 直接调起系统打印组件 (不新开窗口)。
       文档与预览走同一 prepareDocument, 打印介质样式由文档自身 @media print 与 @page 保证。 */
    PP.print = function (html, opts) {
        const options = opts || {};
        const startPrint = function () {
            /* 串行化: 上一次隐藏承载框未清理前先移除, 避免连点堆叠多个打印任务 */
            if (PP._printFrame && PP._printFrame.parentNode) PP._printFrame.parentNode.removeChild(PP._printFrame);
            PP._printFrame = null;
            const doc = prepareDocument(html);
            const paper = PrintDocument.paper(doc, options);
            const frame = document.createElement('iframe');
            frame.title = '打印内容';
            frame.setAttribute('aria-hidden', 'true');
            /* 沙箱与预览一致: allow-same-origin 供父页面访问, allow-modals 放行 print() */
            frame.setAttribute('sandbox', 'allow-same-origin allow-modals');
            frame.style.cssText = 'position:fixed;left:-10000px;top:0;border:0;'
                + 'width:' + (paper.width + GAP * 2) + 'px;height:' + (paper.height + GAP * 2) + 'px;';
            let printed = false;
            const cleanup = function () {
                if (PP._printFrame === frame) PP._printFrame = null;
                if (frame.parentNode) frame.parentNode.removeChild(frame);
            };
            frame.addEventListener('load', function () {
                /* 文档未就绪 (body 空/无内容) 时跳过, 等待 srcdoc 就绪的 load 再打印,
                   避免打出空白页 (与预览 fit 守卫同理) */
                const d = frame.contentDocument;
                if (printed || !d || !d.body || !d.body.firstElementChild) return;
                if (!printContentWindow(frame)) { cleanup(); return; }
                printed = true;
                if (frame.contentWindow) frame.contentWindow.addEventListener('afterprint', cleanup);
                setTimeout(cleanup, 5 * 60 * 1000); /* 兜底: 个别浏览器不派发 afterprint */
            });
            frame.srcdoc = doc; /* 插入前设好 srcdoc, 首个 load 即目标文档, 无 about:blank 竞态 */
            document.body.appendChild(frame);
            PP._printFrame = frame;
            return frame;
        };
        if (typeof options.beforePrint === 'function') {
            Promise.resolve().then(options.beforePrint).then(function (result) {
                if (result !== false) startPrint();
            }).catch(function () { /* 前置失败不打印 */ });
            return null;
        }
        return startPrint();
    };
})();
