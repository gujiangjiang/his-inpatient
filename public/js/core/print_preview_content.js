// public/js/core/print_preview_content.js - 通用预览的文档拼接与标准页眉
(function () {
    'use strict';
    const PP = window.PrintPreview;

    /* 标准单据片段入口：统一构建完整文档后进入同一预览，不允许业务页自行开打印窗口。 */
    PP.openBody = function (title, bodyHtml, opts) {
        const options = Object.assign({ canPrint: true }, opts || {});
        const html = PrintDocument.create(title, bodyHtml, options);
        return PP.open(Format.escape(title || '打印文档') + ' · 打印预览', html, options);
    };

    PP.openElement = function (title, elementOrId, opts) {
        const element = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
        if (!element) { Dom.toast('未找到可打印内容', 'error'); return null; }
        const clone = element.cloneNode(true);
        const sourceFields = element.querySelectorAll('input, textarea, select');
        const cloneFields = clone.querySelectorAll('input, textarea, select');
        sourceFields.forEach(function (field, index) {
            const copy = cloneFields[index];
            if (!copy) return;
            if (field.tagName === 'TEXTAREA') copy.textContent = field.value;
            else if (field.tagName === 'SELECT') {
                Array.from(copy.options).forEach(function (option, optionIndex) {
                    const selected = field.options[optionIndex] && field.options[optionIndex].selected;
                    option.selected = selected;
                    if (selected) option.setAttribute('selected', 'selected');
                    else option.removeAttribute('selected');
                });
            } else {
                copy.setAttribute('value', field.value || '');
                if (field.checked) copy.setAttribute('checked', 'checked');
                else copy.removeAttribute('checked');
            }
        });
        return PP.openBody(title, '<div class="sheet">' + clone.outerHTML + '</div>', opts);
    };

    PP.combine = function (title, htmlList, opts) {
        const docs = (htmlList || []).filter(function (html) { return html && String(html).trim(); });
        if (!docs.length) { Dom.toast('暂无可预览的打印内容', 'info'); return null; }
        if (docs.length === 1) return PP.open(title, docs[0], opts);
        const styles = [], fragments = [];
        docs.forEach(function (html) {
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            parsed.querySelectorAll('script').forEach(function (script) { script.remove(); });
            parsed.querySelectorAll('style').forEach(function (style) {
                if (styles.indexOf(style.textContent) < 0) styles.push(style.textContent);
            });
            const holder = document.createElement('div');
            holder.innerHTML = parsed.body.innerHTML;
            const pages = holder.querySelectorAll('.page, .sheet, .cf-page');
            pages.forEach(function (page, index) {
                const pageNo = page.querySelector('.emr-fp-page, .sheet-page-no');
                if (pageNo) pageNo.textContent = '第 ' + (index + 1) + ' 页 / 共 ' + pages.length + ' 页';
            });
            Array.from(holder.childNodes).forEach(function (node) {
                if (node.nodeType === 1) fragments.push(node.outerHTML);
                else if (node.nodeType === 3 && node.textContent.trim()) fragments.push(node.textContent);
            });
        });
        const safeTitle = Format.escape(title || '打印文档');
        const html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>' + safeTitle + '</title>'
            + '<style>' + styles.join('\n') + '</style></head><body>' + fragments.join('') + '</body></html>';
        return PP.open(title, html, opts);
    };

    PP.sheetHeader = function (info) {
        const esc = function (value) { return Format.escape(String(value == null ? '' : value)); };
        const data = info || {};
        const branding = (typeof HospitalBranding !== 'undefined' && HospitalBranding.get)
            ? HospitalBranding.get() : { hospital_name: data.hospital || '', hospital_name2: '' };
        const fields = [
            ['姓名', data.name], ['性别', data.gender], ['年龄', data.age], ['科别', data.department],
            ['病区', data.ward], ['床号', data.bed], ['住院号', data.admission_no]
        ].filter(function (field) { return field[1] !== undefined && field[1] !== null; });
        const bar = fields.map(function (field) {
            return '<span class="emr-hf"><b>' + esc(field[0]) + '</b><span class="emr-hf-val">' + esc(field[1]) + '</span></span>';
        }).join('');
        return '<div class="pg-head"><div class="brand-name-lockup emr-brand-lockup">'
            + '<div class="brand-name-line emr-hos">' + esc(branding.hospital_name || '') + '</div>'
            + (branding.hospital_name2 ? '<div class="brand-name-line emr-hos2">' + esc(branding.hospital_name2) + '</div>' : '')
            + '</div><div class="emr-docname">' + esc(data.doc_name || '') + '</div>'
            + '<div class="emr-infobar">' + bar + '</div></div>';
    };
})();
