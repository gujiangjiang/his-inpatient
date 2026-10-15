// public/js/orders/cell_picker.js - 医嘱行内编辑悬浮选择器 (途径/频次/执行时间)
// list 模式: 搜索 + × 清空值 + 列表首项 「-」 清空选项; input 模式: 输入 + × 清空
const CellPicker = {
    panel: null,
    onPick: null,
    outside: null,
    esc: null,
    mode: 'list',
    options: [],
    items: [],
    cursor: -1,

    /**
     * opts: { type:'list'|'input', options:[[value,label]], current, placeholder, onPick(value) }
     * event: 触发点击事件 (锚定单元格位置)
     */
    open(opts, event) {
        this.close();
        const rect = (event && event.currentTarget) ? event.currentTarget.getBoundingClientRect() : null;
        const isInput = opts.type === 'input';
        const panel = document.createElement('div');
        panel.className = 'os-pick';
        panel.innerHTML = `
            <div class="os-pick-head">
                <input type="text" class="os-pick-search" placeholder="${Format.escape(opts.placeholder || '搜索…')}" value="${isInput ? Format.escape(opts.current || '') : ''}">
                <button type="button" class="os-pick-clear" title="清空">${svgIcon('common/actions/close', 12)}</button>
            </div>
            ${isInput ? '' : '<div class="os-pick-list"></div>'}`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.onPick = opts.onPick;
        this.mode = isInput ? 'input' : 'list';
        this.options = opts.options || [];
        this.current = opts.current || '';
        if (!isInput) this.renderList('');

        this.position(rect);
        const input = panel.querySelector('.os-pick-search');
        input.focus();
        input.addEventListener('input', () => { if (!isInput) this.renderList(input.value); });
        input.addEventListener('keydown', (e) => this.onKey(e));
        panel.querySelector('.os-pick-clear').addEventListener('click', (e) => {
            e.stopPropagation();
            this.pick('');
        });

        setTimeout(() => {
            this.outside = (e) => { if (this.panel && !this.panel.contains(e.target)) this.close(); };
            document.addEventListener('mousedown', this.outside);
        }, 0);
        this.esc = (e) => { if (e.key === 'Escape') this.close(); };
        document.addEventListener('keydown', this.esc);
    },

    /** 列表渲染: 首项固定 「-」 清空, 其余按搜索词过滤, 当前值高亮 */
    renderList(kw) {
        kw = (kw || '').trim().toLowerCase();
        const opts = this.options.filter(o => o[0] !== '' && (!kw
            || String(o[1] || o[0]).toLowerCase().includes(kw)
            || String(o[0]).toLowerCase().includes(kw)));
        this.items = opts;
        this.cursor = -1;
        const rows = [`<div class="os-pick-item os-pick-empty${this.current === '' ? ' active' : ''}" data-v="">-</div>`]
            .concat(opts.map(o => `<div class="os-pick-item${String(o[0]) === String(this.current) ? ' active' : ''}" data-v="${Format.escape(String(o[0]))}">${Format.escape(o[1] || o[0])}</div>`));
        this.panel.querySelector('.os-pick-list').innerHTML = rows.join('');
        this.panel.querySelectorAll('.os-pick-item').forEach(el => {
            el.addEventListener('click', () => this.pick(el.dataset.v));
        });
    },

    onKey(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (this.mode === 'input') { this.pick(this.panel.querySelector('.os-pick-search').value); return; }
            if (this.cursor >= 0 && this.items[this.cursor]) { this.pick(this.items[this.cursor][0]); return; }
            const visible = this.panel.querySelectorAll('.os-pick-item');
            if (visible.length === 2) this.pick(visible[1].dataset.v);
            return;
        }
        if (this.mode !== 'list') return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const visible = Array.from(this.panel.querySelectorAll('.os-pick-item'));
            const base = this.cursor < 0 ? (e.key === 'ArrowDown' ? -1 : 0) : this.cursor;
            this.cursor = Math.max(0, Math.min(visible.length - 1, base + (e.key === 'ArrowDown' ? 1 : -1)));
            visible.forEach((el, i) => el.classList.toggle('cursor', i === this.cursor));
            visible[this.cursor].scrollIntoView({ block: 'nearest' });
        }
    },

    pick(v) {
        const fn = this.onPick;
        this.close();
        if (fn) fn(v === undefined || v === null ? '' : String(v));
    },

    position(rect) {
        const p = this.panel;
        const w = 200;
        const h = p.offsetHeight || 80;
        let x = rect ? rect.left : (window.innerWidth - w) / 2;
        let y = rect ? rect.bottom + 4 : 160;
        x = Math.max(8, Math.min(x, window.innerWidth - w - 8));
        if (y + h > window.innerHeight - 8) y = Math.max(8, (rect ? rect.top : 200) - h - 4);
        p.style.left = x + 'px';
        p.style.top = y + 'px';
    },

    close() {
        if (this.outside) document.removeEventListener('mousedown', this.outside);
        if (this.esc) document.removeEventListener('keydown', this.esc);
        this.outside = null;
        this.esc = null;
        if (this.panel && this.panel.parentNode) this.panel.parentNode.removeChild(this.panel);
        this.panel = null;
    }
};

window.CellPicker = CellPicker;
