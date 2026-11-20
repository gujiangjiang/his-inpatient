// public/js/core/dom.js
const Dom = {
    toastShown: {}, // 防重复 toast

    toast(message, type = 'error') {
        // 错误只弹一次
        if (type === 'error') {
            const key = message;
            if (this.toastShown[key]) return;
            this.toastShown[key] = true;
            setTimeout(() => delete this.toastShown[key], 3000);
        }

        // 移除已有 toast
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    },

    /**
     * 通用弹窗。契约: title/content 为 HTML 串, 含动态数据时调用方须先 Format.escape。
     * buttons: [{text, type, onclick}] — onclick 返回 false (同步) 或解析为 false 的 Promise 时保留弹窗。
     * opts.large: 弹窗加 modal-lg 宽样式 (原 overlay.querySelector('.modal').classList.add('modal-lg') 样板收敛)。
     */
    modal(title, content, buttons = [], opts = {}) {
        // 支持弹窗叠加 (如开嘱编辑器上再打开药品检索): 不清除已有弹窗, 层级递增
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        const stack = document.querySelectorAll('.modal-overlay').length;
        if (stack > 0) overlay.style.zIndex = 1000 + stack * 10;

        const modal = document.createElement('div');
        modal.className = 'modal' + (opts.large ? ' modal-lg' : '');
        const closeHtml = '<button type="button" class="modal-close" title="关闭">' +
            (window.svgIcon ? svgIcon('common/actions/close', 14) : '×') + '</button>';
        /* 统一关闭: 摘除 Esc 监听 + 安全移除 (onclick 可能已自行关闭弹窗 → 防二次移除抛 NotFoundError) */
        let onKey = null;
        const closeOverlay = () => {
            if (onKey) document.removeEventListener('keydown', onKey);
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        };
        modal.innerHTML = `
            <div class="modal-header"><span class="modal-title">${title}</span>${closeHtml}</div>
            <div class="modal-body">${content}</div>
        `;

        if (buttons.length > 0) {
            const footer = document.createElement('div');
            footer.className = 'modal-footer';
            buttons.forEach(btn => {
                const b = document.createElement('button');
                b.className = 'btn btn-' + (btn.type || 'primary');
                b.textContent = btn.text;
                if (btn.onclick) {
                    b.addEventListener('click', () => {
                        const r = btn.onclick();
                        /* 校验语义: onclick 返回 false (同步) 或 Promise 解析为 false → 保留弹窗 */
                        if (r === false) return;
                        if (r && typeof r.then === 'function') {
                            r.then((v) => { if (v !== false) closeOverlay(); });
                            return;
                        }
                        closeOverlay();
                    });
                } else {
                    b.addEventListener('click', () => closeOverlay());
                }
                footer.appendChild(b);
            });
            modal.appendChild(footer);
        }

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        if (window.SelectPicker) SelectPicker.initInputs(overlay);

        // 关闭交互: X 按钮 / 点击遮罩空白处 / Esc (仅最上层) — 全部走 closeOverlay 统一摘除监听
        overlay.querySelector('.modal-close').addEventListener('click', closeOverlay);
        overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) closeOverlay(); });
        onKey = (e) => {
            if (e.key !== 'Escape') return;
            const all = document.querySelectorAll('.modal-overlay');
            if (all.length && all[all.length - 1] === overlay && document.body.contains(overlay)) {
                closeOverlay();
            } else if (!document.body.contains(overlay)) {
                document.removeEventListener('keydown', onKey);
            }
        };
        document.addEventListener('keydown', onKey);

        return overlay;
    },

    /**
     * 确认弹窗 (删除/作废等二选一确认样板收敛, 原 16+ 处 Dom.modal + 双按钮复制)。
     * message 为 HTML 串, 含动态数据时调用方先转义。
     * opts: { danger: 危险操作红色按钮, okText, cancelText, large }
     */
    confirm(title, message, onOk, opts = {}) {
        return this.modal(title, '<p>' + message + '</p>', [
            { text: opts.okText || '确定', type: opts.danger ? 'danger' : 'primary', onclick: onOk },
            { text: opts.cancelText || '取消', type: 'default' }
        ], opts);
    },

    /**
     * 容器内 <script> 手动提取执行 (innerHTML 注入不会自动跑 script)。
     * 收敛 loadPage 与 sys_configs 懒加载面板的两份同实现。
     */
    execScripts(container) {
        if (!container) return;
        container.querySelectorAll('script').forEach(s => {
            const ns = document.createElement('script');
            ns.textContent = s.textContent;
            s.parentNode.replaceChild(ns, s);
        });
    },

    /** 防抖 (收敛 8 处 250ms 搜索防抖样板)。返回带 cancel 的包装函数。 */
    debounce(fn, wait = 250) {
        let timer = null;
        const wrapped = function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), wait);
        };
        wrapped.cancel = () => clearTimeout(timer);
        return wrapped;
    },

    formField(def, value) {
        // def 优先，value 在后
        const type = def.type || 'text';
        const label = def.label || '';
        const name = def.name || def.key || '';
        const required = def.required ? 'required' : '';
        // 默认整宽表单控件 (form-control), 避免原生 input 窄宽度与全宽下拉混排
        const cls = ' class="' + (def.class || 'form-control') + '"';
        let html = '<div class="form-group">';
        if (label) {
            html += '<label class="form-label">' + Format.escape(label) + '</label>';
        }
        if (type === 'select') {
            html += '<select name="' + name + '"' + cls + ' ' + required + '>';
            if (def.options) {
                def.options.forEach(opt => {
                    const selected = (opt.value == value) ? ' selected' : '';
                    html += '<option value="' + opt.value + '"' + selected + '>' + Format.escape(opt.label) + '</option>';
                });
            }
            html += '</select>';
        } else if (type === 'textarea') {
            html += '<textarea name="' + name + '" ' + cls + ' ' + required + ' rows="' + (def.rows || 4) + '">' + Format.escape(value || '') + '</textarea>';
        } else if (type === 'hidden') {
            html += '<input type="hidden" name="' + name + '" value="' + Format.escape(value || '') + '">';
        } else {
            html += '<input type="' + type + '" name="' + name + '" ' + cls + ' ' + required + ' value="' + Format.escape(value || '') + '">';
        }
        html += '</div>';
        return html;
    },

    getFormData(formId) {
        const form = document.getElementById(formId);
        const data = {};
        const elements = form.querySelectorAll('input, select, textarea');
        elements.forEach(el => {
            if (el.type === 'checkbox') {
                data[el.name] = el.checked;
            } else if (el.type === 'radio') {
                if (el.checked) {
                    data[el.name] = el.value;
                }
            } else {
                data[el.name] = el.value;
            }
        });
        return data;
    }
};
