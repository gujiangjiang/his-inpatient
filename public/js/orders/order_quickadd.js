// public/js/orders/order_quickadd.js - 鼠标旁悬浮快速开嘱 (药品/非药品统一搜索, 点击即入列表)
const OrderQuickAdd = {
    panel: null,
    items: [],
    activeIndex: 0,
    mode: 'all',       // all | medication | nonmedication
    period: 'long',    // long | temp
    timer: null,
    outsideHandler: null,
    escHandler: null,

    open(mode, event) {
        if (this.panel) this.close();
        if (!OrderStation || !OrderStation.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        this.mode = mode || 'all';
        this.period = 'long';
        this.items = [];
        this.activeIndex = 0;

        const panel = document.createElement('div');
        panel.className = 'os-qa';
        panel.innerHTML = `
            <div class="os-qa-head">
                <span class="os-qa-tabs">
                    <button type="button" class="os-qa-tab" data-mode="all">全部</button>
                    <button type="button" class="os-qa-tab" data-mode="medication">药品</button>
                    <button type="button" class="os-qa-tab" data-mode="nonmedication">非药品</button>
                </span>
                <span class="os-qa-period">
                    <label><input type="radio" name="os-qa-period" value="long" checked> 长期</label>
                    <label><input type="radio" name="os-qa-period" value="temp"> 临时</label>
                </span>
            </div>
            <input type="text" class="os-qa-input" placeholder="搜索药品 / 非药品 (名称、拼音首字母、编码), Enter 加入医嘱">
            <div class="os-qa-list"><div class="os-qa-empty">加载中...</div></div>
            <div class="os-qa-hint">↑↓ 选择 · Enter 加入 · Esc 关闭 · 加入后点击医嘱行可修改剂量用法</div>`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.position(event);

        panel.querySelectorAll('.os-qa-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                this.mode = btn.dataset.mode;
                this.updateTabs();
                this.search(this.input().value.trim());
            });
        });
        panel.querySelectorAll('input[name="os-qa-period"]').forEach(radio => {
            radio.addEventListener('change', () => {
                this.period = panel.querySelector('input[name="os-qa-period"]:checked').value;
            });
        });

        const input = this.input();
        input.addEventListener('input', () => {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(input.value.trim()), 180);
        });
        input.addEventListener('keydown', (e) => this.onKey(e));
        input.focus();

        this.updateTabs();
        this.search('');

        setTimeout(() => {
            this.outsideHandler = (e) => {
                if (this.panel && !this.panel.contains(e.target)) this.close();
            };
            this.escHandler = (e) => { if (e.key === 'Escape') this.close(); };
            document.addEventListener('mousedown', this.outsideHandler);
            document.addEventListener('keydown', this.escHandler);
        }, 0);
    },

    input() {
        return this.panel ? this.panel.querySelector('.os-qa-input') : null;
    },

    updateTabs() {
        if (!this.panel) return;
        this.panel.querySelectorAll('.os-qa-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === this.mode);
        });
    },

    position(event) {
        const p = this.panel;
        const w = 480, h = 400;
        let x, y;
        if (event && event.clientX) {
            x = event.clientX + 10;
            y = event.clientY + 8;
        } else {
            x = window.innerWidth / 2 - w / 2;
            y = 96;
        }
        x = Math.min(Math.max(8, x), window.innerWidth - w - 8);
        y = Math.min(Math.max(8, y), window.innerHeight - h - 8);
        p.style.left = x + 'px';
        p.style.top = y + 'px';
    },

    async search(kw) {
        try {
            const qs = new URLSearchParams({ kw: kw, type: this.mode === 'all' ? '' : this.mode });
            const r = await AjaxLoader.api('orders/quick-search?' + qs.toString(), { silent: true });
            this.items = r.data.items || [];
            this.activeIndex = 0;
            this.renderList();
        } catch (e) {
            const list = this.panel && this.panel.querySelector('.os-qa-list');
            if (list) list.innerHTML = '<div class="os-qa-empty">检索失败</div>';
        }
    },

    renderList() {
        const list = this.panel && this.panel.querySelector('.os-qa-list');
        if (!list) return;
        if (this.items.length === 0) {
            list.innerHTML = '<div class="os-qa-empty">未找到匹配项，非药品可直接回车加入自定义内容</div>';
            return;
        }
        list.innerHTML = this.items.map((it, i) => {
            const isMed = it.result_type === 'medication';
            const kind = isMed
                ? '<span class="os-qa-kind k-med">药</span>'
                : '<span class="os-qa-kind k-non">嘱</span>';
            const cls = isMed && it.drug_class ? '<span class="ds-class">[' + Format.escape(it.drug_class) + ']</span>' : '';
            let meta = '';
            if (isMed) {
                meta = [it.specification, it.manufacturer, it.pharmacy, it.dosage_form].filter(Boolean).map(Format.escape).join(' ');
                meta += ' <b style="color:' + (it.stock_quantity > 0 ? '#606266' : '#f56c6c') + '">' + it.stock_quantity + Format.escape(it.unit || '') + '</b>';
            } else {
                meta = [it.usage_name, it.freq_code, it.freq_detail].filter(Boolean).map(Format.escape).join(' ');
            }
            return `<div class="os-qa-row${i === this.activeIndex ? ' active' : ''}" data-idx="${i}">
                ${kind}<span class="os-qa-name">${cls}${Format.escape(it.name || it.content)}</span>
                <span class="os-qa-meta">${meta}</span>
            </div>`;
        }).join('');
        list.querySelectorAll('.os-qa-row').forEach(row => {
            row.addEventListener('click', () => this.pick(parseInt(row.dataset.idx, 10)));
        });
        const active = list.querySelector('.os-qa-row.active');
        if (active) active.scrollIntoView({ block: 'nearest' });
    },

    onKey(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.activeIndex = Math.min(this.activeIndex + 1, this.items.length - 1);
            this.renderList();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this.renderList();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (this.items.length > 0) {
                this.pick(this.activeIndex);
            } else if (this.mode !== 'medication') {
                const text = e.target.value.trim();
                if (text) this.addCustom(text);
            }
        }
    },

    /**
     * 点击/回车: 直接加入医嘱列表 (草稿)
     */
    async pick(idx) {
        const it = this.items[idx];
        if (!it) return;
        const isMed = it.result_type === 'medication';
        await this.addToOrders({
            content: isMed ? it.name : it.content,
            drug_id: isMed ? it.id : null,
            dosage: isMed ? (it.default_dosage || '') : '',
            dosage_unit: isMed ? (it.unit || '') : '',
            usage_name: isMed ? (it.default_route || '') : (it.usage_name || ''),
            usage_rate: isMed ? '' : (it.usage_rate || ''),
            freq_code: isMed ? (it.default_freq || '') : (it.freq_code || ''),
            freq_detail: isMed ? '' : (it.freq_detail || ''),
            billing_flags: isMed ? '' : (it.billing_flags || ''),
            category: it.result_type
        });
    },

    addCustom(text) {
        this.addToOrders({ content: text, category: 'nonmedication' });
    },

    async addToOrders(item) {
        try {
            await AjaxLoader.api('orders/save', { method: 'POST', data: {
                patient_id: OrderStation.patientId,
                order_period: this.period,
                category: item.category,
                items: [item],
                submit: 0
            }});
            Dom.toast('已加入: ' + item.content, 'success');
            OrderStation.refresh();
            const input = this.input();
            if (input) { input.value = ''; input.focus(); }
            this.search('');
        } catch (e) { /* 已提示 */ }
    },

    close() {
        clearTimeout(this.timer);
        if (this.outsideHandler) document.removeEventListener('mousedown', this.outsideHandler);
        if (this.escHandler) document.removeEventListener('keydown', this.escHandler);
        this.outsideHandler = null;
        this.escHandler = null;
        if (this.panel && this.panel.parentNode) this.panel.parentNode.removeChild(this.panel);
        this.panel = null;
    }
};

window.OrderQuickAdd = OrderQuickAdd;
