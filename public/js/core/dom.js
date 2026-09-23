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
        // 移除已有 modal
        const existing = document.querySelector('.modal-overlay');
        if (existing) document.body.removeChild(existing);

        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-header">${title}</div>
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

        return overlay;
    },

    formField(def, value) {
        // def 优先，value 在后
        const type = def.type || 'text';
        const label = def.label || '';
        const name = def.name || def.key || '';
        const required = def.required ? 'required' : '';
        const cls = def.class ? ' class="' + def.class + '"' : '';
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
