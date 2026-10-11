// public/js/core/select_picker.js - 自定义下拉选择组件 (统一风格, 替换原生 select)
// 特性: 搜索栏快速检索 / 列表限高滚动 / 动态数据源 / 选中即写回原生 select (表单兼容)
// 用法:
//   SelectPicker.initInputs(容器)                 自动增强容器内全部 select
//   SelectPicker.attach(selectEl, {small:true})   手动增强 (行内小尺寸)
//   SelectPicker.open(selectEl)                   主动打开
//   SelectPicker.refresh(selectEl)                选项变化后刷新显示
// 动态数据源: select 上加 data-spicker-provider="admin/ward-list", 打开时请求并填充选项
const SelectPicker = {
    activePanel: null,
    activeSelect: null,

    initInputs(root, opts) {
        (root || document).querySelectorAll('select').forEach(sel => {
            if (sel.classList.contains('spicker-native')) return;
            if (sel.dataset.spicker === 'off') return;
            this.attach(sel, opts);
        });
    },

    attach(select, opts) {
        if (select.classList.contains('spicker-native')) return;
        opts = opts || {};
        select.classList.add('spicker-native');
        select.removeAttribute('required');

        const wrap = document.createElement('div');
        wrap.className = 'spicker' + (opts.small ? ' spicker-small' : '') + (select.disabled ? ' spicker-disabled' : '');
        // 继承原生 select 的尺寸约束: 包裹层若不继承 width/flex 会铺满同行, 把旁边元素(输入框/标签)挤压
        const cs = getComputedStyle(select);
        if (select.style.width) wrap.style.width = select.style.width;
        if (cs.flex && cs.flex !== '0 1 auto') wrap.style.flex = cs.flex;
        if (cs.minWidth && cs.minWidth !== 'auto' && cs.minWidth !== '0px') wrap.style.minWidth = cs.minWidth;
        if (cs.maxWidth && cs.maxWidth !== 'none') wrap.style.maxWidth = cs.maxWidth;
        const control = document.createElement('div');
        control.className = 'spicker-control';
        if (!select.disabled) control.tabIndex = 0;
        control.innerHTML = '<span class="spicker-text"></span>' + (window.svgIcon ? svgIcon('common/arrows/caret-down', 11, 'spicker-caret') : '');
        wrap.appendChild(control);
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select._spicker = { wrap: wrap, control: control };

        if (!select.disabled) {
            control.addEventListener('click', (e) => { e.stopPropagation(); this.toggle(select); });
            control.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.toggle(select); }
            });
        }
        select.addEventListener('change', () => this.syncText(select));
        this.syncText(select);
    },

    syncText(select) {
        const st = select._spicker;
        if (!st) return;
        const opt = select.options[select.selectedIndex];
        st.control.querySelector('.spicker-text').textContent = opt ? opt.textContent : '';
        st.control.classList.toggle('spicker-placeholder', !select.value);
        st.wrap.classList.toggle('spicker-disabled', !!select.disabled);
    },

    toggle(select) {
        this.activeSelect === select ? this.close() : this.open(select);
    },

    async open(select) {
        if (select.disabled) return;
        this.close();
        if (!select._spicker) this.attach(select);
        const st = select._spicker;
        this.activeSelect = select;

        const panel = document.createElement('div');
        panel.className = 'spicker-panel';
        panel.innerHTML = `
            <div class="spicker-search"><input type="text" class="spicker-input" placeholder="搜索选项..."></div>
            <div class="spicker-list"><div class="spicker-empty">加载中...</div></div>`;
        document.body.appendChild(panel);
        this.activePanel = panel;
        this.position(select, panel);

        const input = panel.querySelector('.spicker-input');
        const list = panel.querySelector('.spicker-list');
        input.addEventListener('input', () => this.renderList(select, input.value.trim()));
        input.addEventListener('keydown', (e) => this.onKey(e));
        panel.addEventListener('mousedown', (e) => e.stopPropagation());

        // 动态数据源: 打开时请求最新选项
        const provider = select.dataset.spickerProvider;
        if (provider) {
            try {
                const r = await AjaxLoader.api(provider, { silent: true });
                const data = r.data || [];
                select.innerHTML = '';
                data.forEach(it => {
                    const opt = document.createElement('option');
                    if (typeof it === 'string') { opt.value = it; opt.textContent = it; }
                    else { opt.value = it.value; opt.textContent = it.label; }
                    select.appendChild(opt);
                });
            } catch (e) { /* 已提示 */ }
        }
        this.syncText(select);
        this.renderList(select, '');
        input.focus();

        this._outside = (e) => {
            if (this.activePanel && !this.activePanel.contains(e.target) && !st.control.contains(e.target)) this.close();
        };
        this._esc = (e) => { if (e.key === 'Escape') { e.stopPropagation(); this.close(); } };
        setTimeout(() => document.addEventListener('mousedown', this._outside), 0);
        document.addEventListener('keydown', this._esc, true);
    },

    position(select, panel) {
        const rect = select._spicker.control.getBoundingClientRect();
        const w = Math.max(rect.width, select._spicker.wrap.classList.contains('spicker-small') ? 120 : 200);
        const h = 300;
        let x = Math.min(rect.left, window.innerWidth - w - 8);
        let y = rect.bottom + 4;
        if (y + h > window.innerHeight - 8) y = Math.max(8, rect.top - h - 4);
        panel.style.width = w + 'px';
        panel.style.left = Math.max(8, x) + 'px';
        panel.style.top = y + 'px';
    },

    renderList(select, kw) {
        const panel = this.activePanel;
        if (!panel) return;
        const list = panel.querySelector('.spicker-list');
        const opts = Array.from(select.options).filter(o => !kw || (o.textContent || '').toLowerCase().includes(kw.toLowerCase()));
        this._visible = opts;
        if (opts.length === 0) {
            list.innerHTML = '<div class="spicker-empty">无匹配选项</div>';
            return;
        }
        list.innerHTML = opts.map(o =>
            `<div class="spicker-option${o.value === select.value ? ' active' : ''}" data-value="${Format.escape(o.value)}">${Format.escape(o.textContent)}</div>`
        ).join('');
        list.querySelectorAll('.spicker-option').forEach(el => {
            el.addEventListener('click', () => this.pick(el.dataset.value));
        });
        this._cursor = Math.max(0, opts.findIndex(o => o.value === select.value));
        this.highlight();
    },

    highlight() {
        const list = this.activePanel && this.activePanel.querySelector('.spicker-list');
        if (!list) return;
        list.querySelectorAll('.spicker-option').forEach((el, i) => el.classList.toggle('cursor', i === this._cursor));
        const cur = list.querySelector('.spicker-option.cursor');
        if (cur) cur.scrollIntoView({ block: 'nearest' });
    },

    onKey(e) {
        if (!this._visible || !this._visible.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this._cursor = Math.min((this._cursor || 0) + 1, this._visible.length - 1);
            this.highlight();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this._cursor = Math.max((this._cursor || 0) - 1, 0);
            this.highlight();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const opt = this._visible[this._cursor || 0];
            if (opt) this.pick(opt.value);
        }
    },

    pick(value) {
        const select = this.activeSelect;
        if (!select) return this.close();
        select.value = value;
        this.syncText(select);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        this.close(true);
    },

    /** picked=true 表示选中值关闭; 否则取消 (派发 spicker-cancel 供行内编辑还原) */
    close(picked) {
        if (this.activePanel && this.activePanel.parentNode) this.activePanel.parentNode.removeChild(this.activePanel);
        this.activePanel = null;
        if (this._outside) document.removeEventListener('mousedown', this._outside);
        if (this._esc) document.removeEventListener('keydown', this._esc, true);
        this._outside = null;
        this._esc = null;
        if (!picked && this.activeSelect) {
            this.activeSelect.dispatchEvent(new Event('spicker-cancel', { bubbles: true }));
        }
        this.activeSelect = null;
    },

    refresh(select) {
        if (select._spicker) this.syncText(select);
    }
};

window.SelectPicker = SelectPicker;
