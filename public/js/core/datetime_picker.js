// public/js/core/datetime_picker.js - 私有日期/时间选择组件 (全站统一, 不依赖原生控件)
// 用法: input 加属性 data-dtpicker="date|time|datetime", 然后 DateTimePicker.initInputs(容器)
// 类型: date 纯日期 (YYYY-MM-DD) / time 纯时间 (HH:mm) / datetime 日期+时间 (YYYY-MM-DD HH:mm)
const DateTimePicker = {
    popup: null,
    input: null,
    type: 'date',
    view: { y: 2026, m: 1 },
    sel: { y: 2026, m: 1, d: 1, hh: 0, mm: 0 },
    outsideHandler: null,
    escHandler: null,

    initInputs(root) {
        (root || document).querySelectorAll('input[data-dtpicker]').forEach(inp => {
            if (inp.dataset.dtpReady) return;
            inp.dataset.dtpReady = '1';
            inp.addEventListener('focus', () => this.open(inp));
            inp.addEventListener('click', () => this.open(inp));
        });
    },

    open(input) {
        if (this.popup && this.input === input) return;
        this.close();
        this.input = input;
        this.type = input.dataset.dtpicker || 'date';
        const now = new Date();
        const parsed = this.parse(input.value) || {
            y: now.getFullYear(), m: now.getMonth() + 1, d: now.getDate(),
            hh: now.getHours(), mm: now.getMinutes()
        };
        this.sel = parsed;
        this.view = { y: parsed.y, m: parsed.m };

        const panel = document.createElement('div');
        panel.className = 'dtp';
        panel.innerHTML = `
            ${this.type !== 'time' ? `
            <div class="dtp-head">
                <select class="dtp-sel" data-role="year">${this.yearOptions(parsed.y)}</select> 年
                <select class="dtp-sel" data-role="month">${this.monthOptions(parsed.m)}</select> 月
            </div>
            <div class="dtp-week"><span>日</span><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span></div>
            <div class="dtp-days" id="dtp-days"></div>` : ''}
            ${this.type !== 'date' ? `
            <div class="dtp-time">
                <select class="dtp-sel" data-role="hour">${this.hourOptions(parsed.hh)}</select>
                <span>:</span>
                <select class="dtp-sel" data-role="minute">${this.minuteOptions(parsed.mm)}</select>
            </div>` : ''}
            <div class="dtp-foot">
                <span class="dtp-quick" data-role="now">${this.type === 'date' ? '今天' : '现在'}</span>
                <span class="dtp-quick" data-role="clear">清空</span>
                <button type="button" class="btn btn-small btn-primary" data-role="ok">确定</button>
            </div>`;
        document.body.appendChild(panel);
        this.popup = panel;
        this.renderDays();
        this.position();

        panel.querySelectorAll('.dtp-sel').forEach(sel => {
            sel.addEventListener('change', () => {
                const role = sel.dataset.role;
                if (role === 'year') this.view.y = parseInt(sel.value, 10);
                if (role === 'month') this.view.m = parseInt(sel.value, 10);
                if (role === 'hour') this.sel.hh = parseInt(sel.value, 10);
                if (role === 'minute') this.sel.mm = parseInt(sel.value, 10);
                if (role === 'year' || role === 'month') this.renderDays();
            });
        });
        panel.querySelector('[data-role="ok"]').addEventListener('click', () => this.apply());
        panel.querySelector('[data-role="clear"]').addEventListener('click', () => {
            this.input.value = '';
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.close();
        });
        panel.querySelector('[data-role="now"]').addEventListener('click', () => {
            const n = new Date();
            this.sel = { y: n.getFullYear(), m: n.getMonth() + 1, d: n.getDate(), hh: n.getHours(), mm: n.getMinutes() };
            this.view = { y: this.sel.y, m: this.sel.m };
            this.refreshSelects();
            this.renderDays();
        });

        setTimeout(() => {
            this.outsideHandler = (e) => {
                if (this.popup && !this.popup.contains(e.target) && e.target !== this.input) this.close();
            };
            this.escHandler = (e) => { if (e.key === 'Escape') this.close(); };
            document.addEventListener('mousedown', this.outsideHandler);
            document.addEventListener('keydown', this.escHandler);
        }, 0);
    },

    yearOptions(current) {
        const nowY = new Date().getFullYear();
        let html = '';
        for (let y = nowY - 5; y <= nowY + 3; y++) {
            html += `<option value="${y}" ${y === current ? 'selected' : ''}>${y}</option>`;
        }
        return html;
    },

    monthOptions(current) {
        let html = '';
        for (let m = 1; m <= 12; m++) {
            html += `<option value="${m}" ${m === current ? 'selected' : ''}>${m}</option>`;
        }
        return html;
    },

    hourOptions(current) {
        let html = '';
        for (let h = 0; h < 24; h++) {
            html += `<option value="${h}" ${h === current ? 'selected' : ''}>${String(h).padStart(2, '0')}</option>`;
        }
        return html;
    },

    minuteOptions(current) {
        let html = '';
        for (let m = 0; m < 60; m += 5) {
            html += `<option value="${m}" ${m === current ? 'selected' : ''}>${String(m).padStart(2, '0')}</option>`;
        }
        if (current % 5 !== 0) {
            html += `<option value="${current}" selected>${String(current).padStart(2, '0')}</option>`;
        }
        return html;
    },

    refreshSelects() {
        const p = this.popup;
        if (!p) return;
        p.querySelector('[data-role="year"]').value = this.sel.y;
        p.querySelector('[data-role="month"]').value = this.sel.m;
        const h = p.querySelector('[data-role="hour"]');
        const mi = p.querySelector('[data-role="minute"]');
        if (h) h.value = this.sel.hh;
        if (mi) mi.value = this.sel.mm;
    },

    renderDays() {
        const box = this.popup && this.popup.querySelector('#dtp-days');
        if (!box) return;
        const first = new Date(this.view.y, this.view.m - 1, 1);
        const days = new Date(this.view.y, this.view.m, 0).getDate();
        const lead = first.getDay();
        let html = '';
        for (let i = 0; i < lead; i++) html += '<span class="dtp-day dtp-empty"></span>';
        for (let d = 1; d <= days; d++) {
            const selCls = (this.sel.y === this.view.y && this.sel.m === this.view.m && this.sel.d === d) ? ' dtp-active' : '';
            html += `<span class="dtp-day${selCls}" data-day="${d}">${d}</span>`;
        }
        box.innerHTML = html;
        box.querySelectorAll('.dtp-day[data-day]').forEach(el => {
            el.addEventListener('click', () => {
                this.sel.d = parseInt(el.dataset.day, 10);
                this.renderDays();
            });
        });
    },

    position() {
        const rect = this.input.getBoundingClientRect();
        const p = this.popup;
        const w = p.offsetWidth || 270;
        const h = p.offsetHeight || 320;
        let x = Math.min(rect.left, window.innerWidth - w - 8);
        let y = rect.bottom + 4;
        if (y + h > window.innerHeight - 8) y = Math.max(8, rect.top - h - 4);
        p.style.left = Math.max(8, x) + 'px';
        p.style.top = y + 'px';
    },

    parse(value) {
        const s = String(value || '').trim();
        if (!s) return null;
        if (this.type === 'time') {
            const m = s.match(/^(\d{1,2}):(\d{1,2})/);
            if (!m) return null;
            return { y: 2000, m: 1, d: 1, hh: parseInt(m[1], 10), mm: parseInt(m[2], 10) };
        }
        const m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{1,2}))?/);
        if (!m) return null;
        return {
            y: parseInt(m[1], 10), m: parseInt(m[2], 10), d: parseInt(m[3], 10),
            hh: m[4] !== undefined ? parseInt(m[4], 10) : 0,
            mm: m[5] !== undefined ? parseInt(m[5], 10) : 0
        };
    },

    apply() {
        if (!this.input) return;
        const p = this.popup;
        if (p) {
            const h = p.querySelector('[data-role="hour"]');
            const mi = p.querySelector('[data-role="minute"]');
            if (h) this.sel.hh = parseInt(h.value, 10);
            if (mi) this.sel.mm = parseInt(mi.value, 10);
        }
        const p2 = (n) => String(n).padStart(2, '0');
        const date = `${this.sel.y}-${p2(this.sel.m)}-${p2(this.sel.d)}`;
        const time = `${p2(this.sel.hh)}:${p2(this.sel.mm)}`;
        this.input.value = this.type === 'date' ? date : (this.type === 'time' ? time : date + ' ' + time);
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        this.close();
    },

    close() {
        if (this.outsideHandler) document.removeEventListener('mousedown', this.outsideHandler);
        if (this.escHandler) document.removeEventListener('keydown', this.escHandler);
        this.outsideHandler = null;
        this.escHandler = null;
        if (this.popup && this.popup.parentNode) this.popup.parentNode.removeChild(this.popup);
        this.popup = null;
    }
};

window.DateTimePicker = DateTimePicker;
