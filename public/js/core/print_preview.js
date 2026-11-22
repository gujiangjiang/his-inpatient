// public/js/core/print_preview.js - 通用 A4 打印预览模块
// 统一所有「打印预览」入口: 病历 / 护理记录单 / 体温单 / 医嘱单 / 病案首页
// 底层约束: 同一时间只允许一个预览弹窗 (打开新预览自动关闭旧预览);
// PrintPreview.combine(title, htmlList): 多份文档按顺序拼接为一份打印文档, 各文档页码独立计算
// 预览以 iframe 等比缩放显示; opts.canPrint=false 隐藏打印按钮; 预览内禁止右键/复制/选择
(function () {
    'use strict';
    const PP = (window.PrintPreview = {});
    PP._overlay = null;   // 当前预览弹窗 (单例)
    PP._html = '';        // 当前预览的打印文档

    /* 预览样式: 注入到文档样式之前 (默认纸张白底, 文档自身设计色可覆盖), 灰底容器 + 等宽边距 + 纸张分隔 + 禁选择 */
    const PREVIEW_CSS = [
        'html, body { margin: 0; padding: 28px !important; background: #dfe3e8 !important;',
        '  -webkit-user-select: none !important; user-select: none !important; }',
        '.page { background-color: #fff; margin: 0 auto 28px !important; box-shadow: 0 2px 12px rgba(0,0,0,.18); }',
        '.page:last-child { margin-bottom: 0 !important; }',
        '.sheet { background-color: #fff; margin: 0 auto 28px !important; box-shadow: 0 2px 12px rgba(0,0,0,.18); }',
        '.sheet + .sheet { margin-top: 28px !important; }'
    ].join('\n');

    /* 注入到 <head> 之后 (先于文档样式, 设计色优先) */
    const injectPreviewCss = function (docHtml) {
        return String(docHtml || '').replace(/<head[^>]*>/i, function (m) { return m + '<style>' + PREVIEW_CSS + '</style>'; });
    };

    /* 预览内禁止右键/复制/选择/剪切 */
    const guardEvents = function (doc) {
        if (!doc) return;
        ['contextmenu', 'copy', 'cut', 'selectstart'].forEach(function (ev) {
            doc.addEventListener(ev, function (e) { e.preventDefault(); });
        });
    };

    /* 打开预览弹窗 (iframe 等比缩放, 灰底容器内纸张完整显示并居中) */
    PP.open = function (title, html, opts) {
        const o = opts || {};
        const canPrint = o.canPrint !== false;   // 默认可打印
        /* 单例: 同一时间只允许一个预览弹窗 */
        if (PP._overlay && PP._overlay.parentNode) PP._overlay.remove();
        PP._html = html;

        const buttons = [];
        if (canPrint) {
            buttons.push({ text: '打印', type: 'primary', onclick: function () { PP.print(PP._html); } });
        }
        buttons.push({ text: '关闭', type: 'default' });

        const overlay = Dom.modal(title || '打印预览',
            '<div class="pp-scale" id="pp-scale"><iframe class="pp-frame" id="pp-frame" scrolling="no"></iframe></div>',
            buttons);
        overlay.querySelector('.modal').classList.add('modal-cf-preview');
        PP._overlay = overlay;
        const wrap = overlay.querySelector('#pp-scale');
        const frame = overlay.querySelector('#pp-frame');
        wrap.addEventListener('contextmenu', function (e) { e.preventDefault(); });

        /* 缩放适配: iframe 内容宽 = 纸张宽 + 左右边距; 缩放后水平居中, 灰底铺满容器 */
        const fit = function () {
            const doc = frame.contentDocument;
            if (!doc) return;
            guardEvents(doc);
            const pageEl = doc.querySelector('.page, .sheet, .cf-page');
            const paperW = pageEl ? pageEl.offsetWidth : 794;
            const PAD = 28;
            const frameW = paperW + PAD * 2;
            const avail = wrap.clientWidth || 850;
            const s = Math.min(1, avail / frameW);
            const h = Math.max(doc.documentElement.scrollHeight, 2280);
            frame.style.width = frameW + 'px';
            frame.style.height = h + 'px';
            frame.style.transform = 'scale(' + s + ')';
            frame.style.transformOrigin = 'top left';
            /* 水平居中: 缩放后视觉宽度 frameW*s, 左偏移 (avail - frameW*s)/2, 灰底铺满整个容器 */
            frame.style.left = Math.max(0, Math.floor((avail - frameW * s) / 2)) + 'px';
            wrap.style.height = Math.ceil(h * s) + 'px';
        };
        frame.addEventListener('load', fit);
        frame.srcdoc = injectPreviewCss(html);
        setTimeout(fit, 120);
        return overlay;
    };

    /* 多份文档拼接为一份打印文档: 样式合并(去重) + 各文档页码独立计算 + 一次预览 */
    PP.combine = function (title, htmlList, opts) {
        const docs = (htmlList || []).filter(function (h) { return h && String(h).trim(); });
        if (!docs.length) { Dom.toast('暂无可预览的打印内容', 'info'); return; }
        if (docs.length === 1) { PP.open(title, docs[0], opts); return; }
        const styles = [];
        const frags = [];
        docs.forEach(function (h) {
            const d = new DOMParser().parseFromString(h, 'text/html');
            d.querySelectorAll('script').forEach(function (sc) { sc.remove(); });
            d.querySelectorAll('style').forEach(function (st) {
                if (styles.indexOf(st.textContent) < 0) styles.push(st.textContent);
            });
            /* 各文档页码独立计算: 组内纸张连续编页 (组间互不影响) */
            const holder = document.createElement('div');
            holder.innerHTML = d.body.innerHTML;
            const pages = holder.querySelectorAll('.page, .sheet, .cf-page');
            const total = pages.length;
            let idx = 0;
            pages.forEach(function (pg) {
                idx++;
                const pn = pg.querySelector('.emr-fp-page') || pg.querySelector('.sheet-page-no');
                if (pn) pn.textContent = '第 ' + idx + ' 页 / 共 ' + total + ' 页';
            });
            Array.prototype.forEach.call(holder.childNodes, function (n) {
                if (n.nodeType === 1) frags.push(n.outerHTML);
                else if (n.nodeType === 3 && n.textContent.trim()) frags.push(n.textContent);
            });
        });
        const html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + Format.escape(title || '打印文档') + '</title>'
            + '<style>' + styles.join('\n') + '</style></head><body>' + frags.join('') + '</body></html>';
        PP.open(title, html, opts);
    };

    /* 打开系统打印窗口: 剥离脚本后统一注入自动打印 (保证任何文档都能弹出系统打印组件) */
    PP.print = function (html) {
        let doc = String(html || '').replace(/<script[\s\S]*?<\/script>/gi, '');
        doc = doc.replace(/<\/body>/i, '<script>window.onload=function(){setTimeout(function(){window.print();},120);};<\/script></body>');
        const win = window.open('', '_blank', 'width=994,height=1323');
        if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
        win.document.write(doc);
        win.document.close();
    };

    /* 组装 A4 标准页眉 — 与电子病历打印抬头完全一致 (同结构同字号):
     * 医院名 20pt 加粗 + 副名 12pt + 文书名 16pt 加粗居中 + 患者信息栏上下双线 */
    PP.sheetHeader = function (info) {
        const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
        const i = info || {};
        const brand = (HospitalBranding && HospitalBranding.get) ? HospitalBranding.get()
            : { hospital_name: i.hospital || '', hospital_name2: '' };
        const fields = [
            ['姓名', i.name], ['性别', i.gender], ['年龄', i.age], ['科别', i.department],
            ['病区', i.ward], ['床号', i.bed], ['住院号', i.admission_no]
        ].filter(function (f) { return f[1] !== undefined && f[1] !== null; });
        const bar = fields.map(function (f) {
            return '<span class="emr-hf"><b>' + esc(f[0]) + '</b><span class="emr-hf-val">' + esc(f[1]) + '</span></span>';
        }).join('');
        return '<div class="pg-head">'
            + '<div class="brand-name-lockup emr-brand-lockup">'
            + '<div class="brand-name-line emr-hos">' + esc(brand.hospital_name || '') + '</div>'
            + (brand.hospital_name2 ? '<div class="brand-name-line emr-hos2">' + esc(brand.hospital_name2) + '</div>' : '')
            + '</div>'
            + '<div class="emr-docname">' + esc(i.doc_name || '') + '</div>'
            + '<div class="emr-infobar">' + bar + '</div>'
            + '</div>';
    };
})();
