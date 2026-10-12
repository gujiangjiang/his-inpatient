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

    modal(title, content, buttons = []) {
        // 支持弹窗叠加 (如开嘱编辑器上再打开药品检索): 不清除已有弹窗, 层级递增
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        const stack = document.querySelectorAll('.modal-overlay').length;
        if (stack > 0) overlay.style.zIndex = 1000 + stack * 10;

        const modal = document.createElement('div');
        modal.className = 'modal';
        const closeHtml = '<button type="button" class="modal-close" title="关闭">' +
            (window.svgIcon ? svgIcon('common/actions/close', 14) : '×') + '</button>';
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
                        btn.onclick();
                        document.body.removeChild(overlay);
                    });
                } else {
                    b.addEventListener('click', () => document.body.removeChild(overlay));
                }
                footer.appendChild(b);
            });
            modal.appendChild(footer);
        }

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        if (window.SelectPicker) SelectPicker.initInputs(overlay);

        // 关闭交互: X 按钮 / 点击遮罩空白处 / Esc (仅最上层)
        overlay.querySelector('.modal-close').addEventListener('click', () => overlay.remove());
        overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) overlay.remove(); });
        const onKey = (e) => {
            if (e.key !== 'Escape') return;
            const all = document.querySelectorAll('.modal-overlay');
            if (all.length && all[all.length - 1] === overlay && document.body.contains(overlay)) {
                overlay.remove();
                document.removeEventListener('keydown', onKey);
            } else if (!document.body.contains(overlay)) {
                document.removeEventListener('keydown', onKey);
            }
        };
        document.addEventListener('keydown', onKey);

        return overlay;
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
