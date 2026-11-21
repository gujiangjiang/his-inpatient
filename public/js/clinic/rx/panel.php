<script>
var RxPanel = {
    patientId: null,
    catalog: [],
    selected: [],
    kw: '',

    async open() {
        const p = PatientContext.current();
        if (!p) { Dom.toast('请先选择患者', 'info'); return; }
        this.patientId = p.id;
        this.selected = [];
        this.kw = '';
        this.catalog = [];
        this.listTotal = 0;
        this.pharmacyType = 'center';
        this.offline = false;
        this.source = '';
        /* 面板代数令牌: 重开/切换药房/换关键词时递增, 旧请求响应到达后按代数丢弃 */
        this._gen = (this._gen || 0) + 1;
        this.renderSource();   // 初始渲染「加载中」，fetch 完成后按真实 source 更新
        Dom.modal('新方（处方开具）', this.html(), [
            { text: '开具', type: 'primary', onclick: () => this.submit() },
            { text: '取消', type: 'default' }
        ]);
        this._scroll = ScrollLoad.attach(document.getElementById('rx-list'), () => this.fetchList(false));
        this.renderSelected();
        await this.fetchList(true);
    },

    /* 横幅按当前 source 渲染（fetch 返回后必须调用，
       此前只在 modal 打开时渲染一次初始值，导致横幅永远显示「未启用」） */
    renderSource() {
        const el = document.getElementById('rx-source');
        if (!el) return;
        if (this.pharmacyType === 'outpatient') {
            el.innerHTML = (this.source === 'opd'
                ? '项目库来源：门诊药房（实时库存，防止超发）'
                : '项目库来源：门诊药房（' + (this.source === '' ? '加载中…' : this.source === 'cache' ? '离线缓存：实时查询失败，库存/价格可能过期' : '门诊接口未启用/不可用') + '）');
        } else {
            el.innerHTML = '项目库来源：中心药房（本地库存实时）';
        }
    },

    /* 切换药房（中心/门诊） */
    switchPharmacy(type) {
        if (this.pharmacyType === type) return;
        this.pharmacyType = type;
        this.offline = false;
        this.catalog = [];
        this.listTotal = 0;
        this.selected = [];
        this.source = '';
        this._gen = (this._gen || 0) + 1; /* 旧药房请求响应作废 */
        document.querySelectorAll('.rx-ph-type').forEach(b => b.classList.toggle('active', b.dataset.type === type));
        this.renderSource();
        this.renderList();
        this.renderSelected();
        ScrollLoad.reset(this._scroll);
        this.fetchList(true);
    },

    /* 分页拉取目录 (滚动触底续载, 一次 30 条) */
    fetchList(reset) {
        const gen = this._gen || 0;
        if (reset) { this.catalog = []; this.listTotal = 0; }
        if (!reset && this.catalog.length >= this.listTotal) return Promise.resolve(false);
        const params = new URLSearchParams({ limit: 30, offset: this.catalog.length });
        if (this.kw) params.set('kw', this.kw);
        if (this.cat) params.set('category', this.cat);
        params.set('pharmacy_type', this.pharmacyType);
        return AjaxLoader.api('rx/catalog?' + params.toString(), { silent: true }).then(r => {
            if (gen !== (this._gen || 0)) return false; /* 过期响应: 面板已重开/切换, 丢弃 */
            this.source = r.source || this.source;
            this.offline = !!r.offline;
            this.listTotal = r.total != null ? Number(r.total) : (r.data || []).length;
            this.catalog = this.catalog.concat(r.data || []);
            this.renderSource();   // 横幅按真实 source 更新（此前漏调用）
            this.renderList();
            if (this._scroll) ScrollLoad.check(this._scroll);
            return this.catalog.length < this.listTotal;
        }).catch(() => false);
    },

    html() {
        const cats = ['全部'].concat([...new Set(this.catalog.map(i => i.category).filter(Boolean))]);
        return `
        <div class="apply-panel">
            <div class="apply-left">
                <div class="rx-ph-tabs">
                    <button type="button" class="btn btn-small rx-ph-type active" data-type="center" onclick="RxPanel.switchPharmacy('center')">中心药房</button>
                    <button type="button" class="btn btn-small rx-ph-type" data-type="outpatient" onclick="RxPanel.switchPharmacy('outpatient')">门诊药房</button>
                </div>
                <input class="form-control" id="rx-kw" placeholder="搜索药品名称 / 规格 / 厂家（F9）" oninput="RxPanel.onKw(this.value)">
                <div class="apply-source" id="rx-source"></div>
                <div class="apply-cats" id="rx-cats">
                    ${cats.map((c, i) => `<button type="button" class="apply-chip${i === 0 ? ' active' : ''}" data-cat="${i === 0 ? '' : Format.escape(c)}" onclick="RxPanel.onCat(this)">${Format.escape(c)}</button>`).join('')}
                </div>
                <div class="apply-list" id="rx-list"></div>
            </div>
            <div class="apply-right">
                <div class="flex-between apply-sel-head">
                    <span>已选 <b id="rx-count">0</b> 项</span>
                    <span>参考总价 <b class="apply-total" id="rx-total">¥0.00</b></span>
                </div>
                <div class="apply-sel" id="rx-sel"></div>
            </div>
        </div>`;
    },

    onKw(v) {
        this.kw = v.trim();
        clearTimeout(this._kwTimer);
        this._kwTimer = setTimeout(() => {
            this._gen = (this._gen || 0) + 1; /* 旧关键词请求作废 */
            ScrollLoad.reset(this._scroll);
            this.fetchList(true);
        }, 250);
    },

    onCat(el) {
        document.querySelectorAll('#rx-cats .apply-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        this.cat = el.dataset.cat;
        this._gen = (this._gen || 0) + 1; /* 旧分类请求作废 */
        ScrollLoad.reset(this._scroll);
        this.fetchList(true);
    },

    filtered() {
        return this.catalog.filter(i =>
            (!this.cat || i.category === this.cat) &&
            (!this.kw || i.name.toLowerCase().includes(this.kw.toLowerCase())
                || (i.spec || '').toLowerCase().includes(this.kw.toLowerCase())
                || (i.vendor_short || '').includes(this.kw))
        );
    },

    renderList() {
        const list = document.getElementById('rx-list');
        if (!list) return;
        const rows = this.filtered();
        if (!rows.length) { list.innerHTML = '<div class="empty-state">无匹配药品</div>'; return; }
        list.innerHTML = rows.map(i => {
            const sel = this.selected.includes(i.id) ? ' selected' : '';
            const meta = [i.spec, i.vendor_short, i.unit].filter(Boolean).join(' · ');
            const defaults = [i.single_dose, i.frequency_text, i.route].filter(Boolean).join(' · ');
            return `<div class="apply-item${sel}" onclick="RxPanel.toggle(${i.id})">
                <div class="ai-main">
                    <span class="ai-name">${Format.escape(i.name)}</span>
                    ${i.category ? `<span class="ai-tag">${Format.escape(i.category)}</span>` : ''}
                </div>
                <div class="ai-meta">${Format.escape(meta)}</div>
                ${defaults ? `<div class="ai-note">${Format.escape(defaults)}</div>` : ''}
                <div class="ai-price">¥${Number(i.price || 0).toFixed(2)}</div>
            </div>`;
        }).join('');
    },

    toggle(id) {
        const idx = this.selected.indexOf(id);
        if (idx === -1) this.selected.push(id);
        else this.selected.splice(idx, 1);
        this.renderList();
        this.renderSelected();
    },

    itemById(id) { return this.catalog.find(i => i.id == id); },

    renderSelected() {
        const box = document.getElementById('rx-sel');
        if (!box) return;
        const items = this.selected.map(id => this.itemById(id)).filter(Boolean);
        document.getElementById('rx-count').textContent = items.length;
        document.getElementById('rx-total').textContent = '¥' + items.reduce((s, i) => s + Number(i.price || 0), 0).toFixed(2);
        if (!items.length) { box.innerHTML = '<div class="empty-state">点击左侧药品加入</div>'; return; }
        box.innerHTML = items.map(i => {
            const defaults = [i.single_dose, i.frequency_text, i.route].filter(Boolean).join(' · ') || '默认用法待补充';
            return `
            <div class="apply-sel-row">
                <span class="asr-name">${Format.escape(i.name)}<span class="rx-defaults">${Format.escape(defaults)}</span></span>
                <span class="asr-price">¥${Number(i.price || 0).toFixed(2)}</span>
                <button type="button" class="asr-x" onclick="RxPanel.toggle(${i.id})">×</button>
            </div>`;
        }).join('');
    },

    async submit() {
        if (!this.selected.length) { Dom.toast('请先选择药品', 'error'); return; }
        const items = this.selected.map(id => {
            const i = this.itemById(id);
            return {
                content: i.name,
                dosage: i.single_dose || '',
                usage_name: i.route || '',
                freq_code: i.frequency_code || '',
                category: 'medication',
                order_period: 'temp',
                drug_id: i.source === 'center' ? (i.id || null) : null,  // 中心药房关联本地药品；门诊药房不关联
                pharmacy_type: i.source === 'outpatient' || this.pharmacyType === 'outpatient' ? 'outpatient' : 'center',
                qty: i.stock != null ? Number(i.stock) : 0,   // 门诊实时库存快照（供 UI 展示，摆药时以实时接口为准）
            };
        });
        // 处方直接提交 (待护士核对), 医嘱备注「处方」以便医嘱列表识别来源
        // group:0 - 处方内多个药品为独立医嘱, 不成组 (不显示成组括号与组头用法)
        const r = await AjaxLoader.api('orders/save', { method: 'POST', data: {
            patient_id: this.patientId,
            order_period: 'temp',
            category: 'medication',
            items: items,
            submit: 1,
            group: 0,
            notes: '处方',
            pharmacy_type: this.pharmacyType,   // 处方选药房：center/outpatient
        }});
        if (r.status === 'success') {
            Dom.toast('处方已开具（待护士核对）', 'success');
            clinic_rx_load();
        }
    }
};
</script>
