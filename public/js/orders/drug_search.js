// public/js/orders/drug_search.js - 药品模糊检索弹窗 (↑↓ 选择, Enter 确认, Esc 关闭)
const DrugSearch = {
    overlay: null,
    rows: [],
    activeIndex: 0,
    onPick: null,
    timer: null,

    open(onPick) {
        this.onPick = onPick;
        this.rows = [];
        this.activeIndex = 0;

        const html = `
            <div class="drug-search">
                <div class="ds-bar">
                    <span>药品</span>
                    <input type="text" id="ds-input" class="ds-input" placeholder="输入药品名称 / 拼音码 / 编码, 回车检索">
                </div>
                <div id="ds-list" class="ds-list"><div class="ds-empty">加载中...</div></div>
                <div class="ds-hint"><b>↑↓</b> 移动　<b>Enter</b> 选择　<b>Esc</b> 关闭　支持名称/拼音首字母/编码</div>
            </div>`;

        this.overlay = Dom.modal('药品检索', html, [{ text: '关闭', type: 'default' }]);
        const input = document.getElementById('ds-input');
        input.focus();

        input.addEventListener('input', () => {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(input.value.trim()), 200);
        });
        input.addEventListener('keydown', (e) => this.onKey(e));

        this.search('');
    },

    async search(kw) {
        try {
            const r = await AjaxLoader.api('pharmacy/drug-search?kw=' + encodeURIComponent(kw), { silent: true });
            this.rows = r.data || [];
            this.activeIndex = 0;
            this.render();
        } catch (e) {
            const list = document.getElementById('ds-list');
            if (list) list.innerHTML = '<div class="ds-empty">检索失败</div>';
        }
    },

    render() {
        const list = document.getElementById('ds-list');
        if (!list) return;
        if (this.rows.length === 0) {
            list.innerHTML = '<div class="ds-empty">未找到匹配药品</div>';
            return;
        }
        list.innerHTML = this.rows.map((m, i) => {
            const cls = m.drug_class ? '<span class="ds-class">[' + Format.escape(m.drug_class) + ']</span>' : '';
            const stockCls = m.stock_quantity > 0 ? '' : ' low';
            return `<div class="ds-row${i === this.activeIndex ? ' active' : ''}" data-idx="${i}">
                <div class="ds-name">${cls}${Format.escape(m.name)}</div>
                <div class="ds-spec">${Format.escape(m.specification || '')} ${Format.escape(m.manufacturer || '')} ${Format.escape(m.pharmacy || '')} ${Format.escape(m.dosage_form || '')}</div>
                <div class="ds-stock${stockCls}">${m.stock_quantity} ${Format.escape(m.unit || '')}</div>
            </div>`;
        }).join('');

        list.querySelectorAll('.ds-row').forEach(row => {
            row.addEventListener('click', () => this.pick(parseInt(row.dataset.idx, 10)));
            row.addEventListener('dblclick', () => this.pick(parseInt(row.dataset.idx, 10)));
        });
        this.scrollToActive();
    },

    scrollToActive() {
        const list = document.getElementById('ds-list');
        const el = list && list.querySelector('.ds-row.active');
        if (el) el.scrollIntoView({ block: 'nearest' });
    },

    onKey(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.activeIndex = Math.min(this.activeIndex + 1, this.rows.length - 1);
            this.render();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this.render();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            this.pick(this.activeIndex);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            this.close();
        }
    },

    pick(idx) {
        const drug = this.rows[idx];
        if (!drug) return;
        const cb = this.onPick;
        this.close();
        if (typeof cb === 'function') cb(drug);
    },

    close() {
        if (this.overlay && this.overlay.parentNode) {
            this.overlay.parentNode.removeChild(this.overlay);
        }
        this.overlay = null;
    }
};

window.DrugSearch = DrugSearch;
