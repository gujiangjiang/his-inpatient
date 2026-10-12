// public/js/clinic/exam_panel.js - 检查申请面板 (分类多选 → 生成检查医嘱; 项目库预留对接门诊)
const ExamPanel = {
    patientId: null,
    catalog: [],
    selected: [],
    cat: '',
    kw: '',

    async open() {
        const p = PatientContext.current();
        if (!p) { Dom.toast('请先选择患者', 'info'); return; }
        this.patientId = p.id;
        this.selected = [];
        this.cat = '';
        this.kw = '';
        const c = await AjaxLoader.api('exam/catalog', { silent: true });
        this.catalog = c.data || [];
        this.source = c.source || 'local';
        Dom.modal('检查申请', this.html(), [
            { text: '开具', type: 'primary', onclick: () => this.submit() },
            { text: '取消', type: 'default' }
        ]);
        this.renderList();
        this.renderSelected();
    },

    html() {
        const cats = ['全部'].concat([...new Set(this.catalog.map(i => i.category).filter(Boolean))]);
        return `
        <div class="apply-panel">
            <div class="apply-left">
                <input class="form-control" id="exam-kw" placeholder="搜索检查项目" oninput="ExamPanel.onKw(this.value)">
                <div class="apply-cats" id="exam-cats">
                    ${cats.map((c, i) => `<button type="button" class="apply-chip${i === 0 ? ' active' : ''}" data-cat="${i === 0 ? '' : Format.escape(c)}" onclick="ExamPanel.onCat(this)">${Format.escape(c)}</button>`).join('')}
                </div>
                <div class="apply-list" id="exam-list"></div>
                <div class="apply-hint">${this.source === 'opd' ? '项目库来源：门诊一体化系统（审核通过项，实时同步）' : '项目库来源：本地虚拟数据（预留接口对接门诊一体化检查项目库）'} <span class="apply-warn">注：请一次性开好所有的项目！</span></div>
            </div>
            <div class="apply-right">
                <div class="flex-between apply-sel-head">
                    <span>已选 <b id="exam-count">0</b> 项</span>
                    <span>参考总价 <b class="apply-total" id="exam-total">¥0.00</b></span>
                </div>
                <div class="apply-sel" id="exam-sel"></div>
            </div>
        </div>`;
    },

    onKw(v) { this.kw = v.trim(); this.renderList(); },

    onCat(el) {
        document.querySelectorAll('#exam-cats .apply-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        this.cat = el.dataset.cat;
        this.renderList();
    },

    filtered() {
        return this.catalog.filter(i =>
            (!this.cat || i.category === this.cat) &&
            (!this.kw || i.name.toLowerCase().includes(this.kw.toLowerCase()) || (i.sub_category || '').includes(this.kw))
        );
    },

    renderList() {
        const list = document.getElementById('exam-list');
        if (!list) return;
        const rows = this.filtered();
        if (!rows.length) { list.innerHTML = '<div class="empty-state">无匹配项目</div>'; return; }
        list.innerHTML = rows.map(i => {
            const sel = this.selected.includes(i.id) ? ' selected' : '';
            return `<div class="apply-item${sel}" onclick="ExamPanel.toggle(${i.id})">
                <div class="ai-main">
                    <span class="ai-name">${Format.escape(i.name)}</span>
                    <span class="ai-tag">${Format.escape(i.category || '')}</span>
                    ${i.sub_category ? `<span class="ai-tag ai-tag-plain">${Format.escape(i.sub_category)}</span>` : ''}
                </div>
                <div class="ai-meta">${Format.escape(i.dept || '')}</div>
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
        const box = document.getElementById('exam-sel');
        if (!box) return;
        const items = this.selected.map(id => this.itemById(id)).filter(Boolean);
        document.getElementById('exam-count').textContent = items.length;
        document.getElementById('exam-total').textContent = '¥' + items.reduce((s, i) => s + Number(i.price || 0), 0).toFixed(2);
        if (!items.length) { box.innerHTML = '<div class="empty-state">点击左侧项目加入</div>'; return; }
        box.innerHTML = items.map(i => `
            <div class="apply-sel-row">
                <span class="asr-name">${Format.escape(i.name)}</span>
                <span class="asr-meta">${Format.escape(i.category || '')}</span>
                <span class="asr-price">¥${Number(i.price || 0).toFixed(2)}</span>
                <button type="button" class="asr-x" onclick="ExamPanel.toggle(${i.id})">×</button>
            </div>`).join('');
    },

    async submit() {
        if (!this.selected.length) { Dom.toast('请先选择检查项目', 'error'); return; }
        const r = await AjaxLoader.api('exam/apply', { method: 'POST', data: {
            patient_id: this.patientId, item_ids: this.selected
        }});
        if (r.status === 'success') {
            Dom.toast(r.message, 'success');
            if (typeof outpatient_exams_loadApply === 'function') outpatient_exams_loadApply();
        }
    }
};
