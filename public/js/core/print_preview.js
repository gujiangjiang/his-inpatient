// public/js/core/print_preview.js - 通用 A4 打印预览模块
// 统一所有「打印预览」入口: 病历 / 护理记录单 / 体温单 / 医嘱单 / 病案首页
// 预览以 iframe 100% 原尺寸渲染 + transform 等比缩放 (与打印一致);
// opts.canPrint=false 时隐藏打印按钮 (仅供预览, 如护士/医生查看病历);
// 调用: PrintPreview.open(title, html, { canPrint: true|false })
(function () {
    'use strict';
    const PP = (window.PrintPreview = {});

    /* 弹窗内 iframe 预览 (A4 794px 基准) */
    PP.open = function (title, html, opts) {
        const o = opts || {};
        const canPrint = o.canPrint !== false;   // 默认可打印
        const buttons = [];
        if (canPrint) {
            buttons.push({
                text: '打印', type: 'primary', onclick: function () {
                    const win = window.open('', '_blank', 'width=994,height=1323');
                    if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
                    win.document.write(html);
                    win.document.close();
                }
            });
        }
        buttons.push({ text: '关闭', type: 'default' });

        const overlay = Dom.modal(title || '打印预览',
            '<div class="pp-scale" id="pp-scale"><iframe class="pp-frame" id="pp-frame" scrolling="no"></iframe></div>',
            buttons);
        overlay.querySelector('.modal').classList.add('modal-cf-preview');
        const wrap = overlay.querySelector('#pp-scale');
        const frame = overlay.querySelector('#pp-frame');
        /* 预览专用样式注入: 灰底容器 + 纸张白底 + 四周等宽边距 + 纸张间分隔 (仅预览, 打印窗口仍走原文档 @page) */
        const previewCss = [
            'html, body { background: #dfe3e8 !important; margin: 0; padding: 28px !important; }',
            '.page { background: #fff !important; margin: 0 auto 28px !important; box-shadow: 0 2px 12px rgba(0,0,0,.18); }',
            '.page:last-child { margin-bottom: 0 !important; }',
            '.sheet { background: #fff !important; margin: 0 auto 28px !important; box-shadow: 0 2px 12px rgba(0,0,0,.18); }',
            '.sheet + .sheet { margin-top: 28px !important; }'
        ].join('\n');
        const injectPreviewCss = function (docHtml) {
            return String(docHtml || '').replace(/<\/head>/i, '<style>' + previewCss + '</style></head>');
        };
        const fit = function () {
            const avail = wrap.clientWidth || 820;
            const s = Math.min(1, avail / 794);
            const doc = frame.contentDocument;
            const h = doc ? Math.max(doc.documentElement.scrollHeight, 2280) : 2280;
            frame.style.width = '794px';
            frame.style.height = h + 'px';
            frame.style.transform = 'scale(' + s + ')';
            frame.style.transformOrigin = 'top left';
            wrap.style.height = Math.ceil(h * s) + 'px';
        };
        frame.addEventListener('load', fit);
        frame.srcdoc = injectPreviewCss(html);
        setTimeout(fit, 120);
        return overlay;
    };

    /* 便捷: 打印预览并自动打印 (医生端病历/文书等) */
    PP.print = function (html) {
        const win = window.open('', '_blank', 'width=994,height=1323');
        if (!win) { Dom.toast('浏览器拦截了打印窗口, 请允许弹出窗口', 'error'); return; }
        win.document.write(html);
        win.document.close();
    };

    /* 组装 A4 标准页眉 (医院名 + 文书名 + 患者信息栏) — 供护理记录单/体温单/医嘱单等文档复用
     * 与电子病历打印页眉视觉一致: 医院名上方, 文书名居中, 信息栏上下双线 */
    PP.sheetHeader = function (info) {
        const esc = function (s) { return Format.escape(String(s == null ? '' : s)); };
        const i = info || {};
        const fields = [
            ['姓名', i.name], ['性别', i.gender], ['年龄', i.age], ['科别', i.department],
            ['病区', i.ward], ['床号', i.bed], ['住院号', i.admission_no]
        ].filter(function (f) { return f[1] !== undefined && f[1] !== null; });
        const bar = fields.map(function (f) {
            return '<span class="emr-hf"><b>' + esc(f[0]) + '</b><span class="emr-hf-val">' + esc(f[1]) + '</span></span>';
        }).join('');
        const hos = HospitalBranding && HospitalBranding.headerHtml ? HospitalBranding.headerHtml('') : '';
        return '<div class="pg-head">'
            + (hos || '<div class="brand-name-lockup emr-brand-lockup"><div class="brand-name-line emr-hos">' + esc(i.hospital || '') + '</div></div>')
            + '<div class="emr-docname">' + esc(i.doc_name || '') + '</div>'
            + '<div class="emr-infobar">' + bar + '</div>'
            + '</div>';
    };
})();