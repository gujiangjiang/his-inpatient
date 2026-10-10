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

    open(mode, event, onPick) {
        if (this.panel) this.close();
        if (!OrderStation || !OrderStation.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        // 药品/非药品分开检索; 默认期间参照上一条医嘱 (开具后可点长/临切换)
        this.mode = mode === 'nonmedication' ? 'nonmedication' : 'medication';
        this.period = (typeof OrderStation.lastOrderPeriod === 'function') ? OrderStation.lastOrderPeriod() : 'long';
        this.onPickCb = (typeof onPick === 'function') ? onPick : null;
        this.items = [];
        this.activeIndex = 0;

        const hint = this.onPickCb
            ? '↑↓ 选择 · Enter 加入成组 · Esc 关闭'
            : '↑↓ 选择 · Enter 加入 · Esc 关闭 · 加入后点击医嘱内容文字可修改';
        const panel = document.createElement('div');
        panel.className = 'os-qa';
        panel.innerHTML = `
            <div class="os-qa-head">
                <span class="os-qa-title">${this.mode === 'medication' ? '药品' : '非药品'}${this.onPickCb ? ' (加入成组)' : ''}</span>
                <span class="os-qa-period-hint">默认: ${this.period === 'temp' ? '临时' : '长期'}医嘱 (参照上一条, 开具后可点长/临切换)</span>
            </div>
            <input type="text" class="os-qa-input" placeholder="搜索${this.mode === 'medication' ? '药品 (名称、拼音首字母、编码)' : '非药品 (名称)'}, Enter 加入医嘱">
            <div class="os-qa-list"><div class="os-qa-empty">加载中...</div></div>
            <div class="os-qa-hint">${hint}</div>`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.position(event);

        const input = this.input();
        input.addEventListener('input', () => {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(input.value.trim()), 180);
        });
        input.addEventListener('keydown', (e) => this.onKey(e));
        input.focus();
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
            // 非药品显示类别标签 (护理/饮食/测量等, 仅搜索展示, 不开具进医嘱)
            const kind = isMed
                ? '<span class="os-qa-kind k-med">药</span>'
                : `<span class="os-qa-kind k-non">${Format.escape(it.catalog_group || '嘱')}</span>`;
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
    /**
     * 点击/回车: 直接加入医嘱列表 (草稿); 成组模式则回调选择结果
     */
    async pick(idx) {
        const it = this.items[idx];
        if (!it) return;
        const isMed = it.result_type === 'medication';
        const freqCode = isMed ? (it.default_freq || '') : (it.freq_code || '');
        const auto = (typeof OrderStation !== 'undefined') ? OrderStation.autoFreqDetail(freqCode) : '';
        const item = {
            content: isMed ? it.name : it.content,
            drug_id: isMed ? it.id : null,
            dosage: isMed ? (it.default_dosage || '') : '',
            dosage_unit: isMed ? (it.unit || '') : '',
            usage_name: isMed ? (it.default_route || '') : (it.usage_name || ''),
            usage_rate: isMed ? '' : (it.usage_rate || ''),
            freq_code: freqCode,
            freq_detail: (it.freq_detail || '') || auto,
            billing_flags: isMed ? '' : (it.billing_flags || ''),
            category: it.result_type
        };
        if (this.onPickCb) {
            const cb = this.onPickCb;
            this.close();
            cb(item);
            return;
        }
        await this.addToOrders(item);
    },

    addCustom(text) {
        this.addToOrders({ content: text, category: 'nonmedication' });
    },

    async addToOrders(item) {
        // 临时医嘱无频次/执行时间
        if (this.period === 'temp') { item.freq_code = ''; item.freq_detail = ''; }
        try {
            const r = await AjaxLoader.api('orders/save', { method: 'POST', data: {
                patient_id: OrderStation.patientId,
                order_period: this.period,
                category: item.category,
                items: [item],
                submit: 0
            }});
            Dom.toast('已实时保存草稿: ' + item.content, 'success');
            const newId = r.data && r.data.id ? Number(r.data.id) : null;
            OrderStation.refresh(false, newId);
            const input = this.input();
            if (input) { input.value = ''; input.focus(); }
            this.search('');
        } catch (e) { /* 已提示 */ }
    },

    /** 仅选择药品/非药品, 选中后回调 (用于成组等场景) */
    openPick(mode, event, onPick) {
        this.open(mode, event, onPick);
    },

    close() {
        clearTimeout(this.timer);
        if (this.outsideHandler) document.removeEventListener('mousedown', this.outsideHandler);
        if (this.escHandler) document.removeEventListener('keydown', this.escHandler);
        this.outsideHandler = null;
        this.escHandler = null;
        this.onPickCb = null;
        if (this.panel && this.panel.parentNode) this.panel.parentNode.removeChild(this.panel);
        this.panel = null;
    }
};

window.OrderQuickAdd = OrderQuickAdd;
