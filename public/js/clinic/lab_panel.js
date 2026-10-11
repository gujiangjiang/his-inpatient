// public/js/clinic/lab_panel.js - 检验申请面板 (多选项目 → 生成检验医嘱 + 套组模板)
const LabPanel = {
    patientId: null,
    catalog: [],
    panels: [],
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
        const [c, pn] = await Promise.all([
            AjaxLoader.api('lab/catalog', { silent: true }),
            AjaxLoader.api('lab/panel-list', { silent: true })
        ]);
        this.catalog = c.data || [];
        this.source = c.source || 'local';
        this.panels = pn.data || [];
        Dom.modal('检验申请', this.html(), [
            { text: '保存', type: 'primary', onclick: () => this.submit(0) },
            { text: '保存并提交', type: 'success', onclick: () => this.submit(1) },
            { text: '取消', type: 'default' }
        ]);
        this.renderList();
        this.renderSelected();
        this.renderPanels();
    },

    html() {
        const cats = ['全部'].concat([...new Set(this.catalog.map(i => i.category).filter(Boolean))]);
        return `
        <div class="apply-panel">
            <div class="apply-left">
                <input class="form-control" id="lab-kw" placeholder="搜索项目名称" oninput="LabPanel.onKw(this.value)">
                <div class="apply-source">${this.source === 'opd' ? '项目库来源：门诊一体化系统（审核通过项，实时同步）' : '项目库来源：本地虚拟库（门诊接口未启用/不可用）'} <span class="apply-warn">注：请一次性开好所有的项目！</span></div>
                <div class="apply-cats" id="lab-cats">
                    ${cats.map((c, i) => `<button type="button" class="apply-chip${i === 0 ? ' active' : ''}" data-cat="${i === 0 ? '' : Format.escape(c)}" onclick="LabPanel.onCat(this)">${Format.escape(c)}</button>`).join('')}
                </div>
                <div class="apply-list" id="lab-list"></div>
            </div>
            <div class="apply-right">
                <div class="flex-between apply-sel-head">
                    <span>已选 <b id="lab-count">0</b> 项</span>
                    <span>参考总价 <b class="apply-total" id="lab-total">¥0.00</b></span>
                </div>
                <div class="apply-sel" id="lab-sel"></div>
                <div class="apply-tools">
                    <button type="button" class="btn btn-small" onclick="LabPanel.saveAsPanel()">存为套组</button>
                </div>
                <div class="apply-panels" id="lab-panels"></div>
            </div>
        </div>`;
    },

    onKw(v) { this.kw = v.trim(); this.renderList(); },

    onCat(el) {
        document.querySelectorAll('#lab-cats .apply-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        this.cat = el.dataset.cat;
        this.renderList();
    },

    filtered() {
        return this.catalog.filter(i =>
            (!this.cat || i.category === this.cat) &&
            (!this.kw || i.name.toLowerCase().includes(this.kw.toLowerCase()))
        );
    },

    renderList() {
        const list = document.getElementById('lab-list');
        if (!list) return;
        const rows = this.filtered();
        if (!rows.length) { list.innerHTML = '<div class="empty-state">无匹配项目</div>'; return; }
        list.innerHTML = rows.map(i => {
            const sel = this.selected.includes(i.id) ? ' selected' : '';
            const isGroup = i.is_group == 1 && i.members && i.members.length;
            const tag = isGroup ? `<span class="ai-tag">组合 ${i.members.length} 项</span>`
                : (i.category ? `<span class="ai-tag">${Format.escape(i.category)}</span>` : '');
            const meta = [i.specimen, i.dept, i.report_time].filter(Boolean).join(' · ');
            const noteText = isGroup ? i.members.map(m => m.name).join('、') : (i.note || '');
            return `<div class="apply-item${sel}" onclick="LabPanel.toggle(${i.id})">
                <div class="ai-main">
                    <span class="ai-name">${Format.escape(i.name)}</span>
                    ${tag}
                </div>
                <div class="ai-meta">${Format.escape(meta)}</div>
                ${noteText ? `<div class="ai-note">${Format.escape(noteText)}</div>` : ''}
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
        const box = document.getElementById('lab-sel');
        if (!box) return;
        const items = this.selected.map(id => this.itemById(id)).filter(Boolean);
        document.getElementById('lab-count').textContent = items.length;
        document.getElementById('lab-total').textContent = '¥' + items.reduce((s, i) => s + Number(i.price || 0), 0).toFixed(2);
        if (!items.length) { box.innerHTML = '<div class="empty-state">点击左侧项目加入</div>'; return; }
        box.innerHTML = items.map(i => {
            const isGroup = i.is_group == 1 && i.members && i.members.length;
            const meta = isGroup ? ('组合 ' + i.members.length + ' 项') : (i.specimen || '');
            return `
            <div class="apply-sel-row">
                <span class="asr-name">${Format.escape(i.name)}</span>
                <span class="asr-meta">${Format.escape(meta)}</span>
                <span class="asr-price">¥${Number(i.price || 0).toFixed(2)}</span>
                <button type="button" class="asr-x" onclick="LabPanel.toggle(${i.id})">×</button>
            </div>`;
        }).join('');
    },

    renderPanels() {
        const box = document.getElementById('lab-panels');
        if (!box) return;
        if (!this.panels.length) { box.innerHTML = ''; return; }
        box.innerHTML = '<div class="apply-panels-title">常用套组</div>' + this.panels.map(p => `
            <span class="apply-panel-chip">
                <a onclick="LabPanel.applyPanel(${p.id})">${Format.escape(p.name)} (${p.items.length})</a>
                <button type="button" title="删除套组" onclick="LabPanel.deletePanel(${p.id})">×</button>
            </span>`).join('');
    },

    applyPanel(id) {
        const p = this.panels.find(x => x.id == id);
        if (!p) return;
        this.selected = [...new Set([...this.selected, ...p.items.map(Number)])].filter(x => this.itemById(x));
        this.renderList();
        this.renderSelected();
        Dom.toast('已套用「' + p.name + '」', 'success');
    },

    saveAsPanel() {
        if (!this.selected.length) { Dom.toast('请先选择项目', 'error'); return; }
        const ids = [...this.selected];
        Dom.modal('存为常用套组', `
            <div class="form-group"><label class="form-label">套组名称</label>
            <input class="form-control" id="lab-panel-name" placeholder="如：血常规+生化全套"></div>`, [
            { text: '保存', type: 'primary', onclick: async function() {
                const name = (document.getElementById('lab-panel-name').value || '').trim();
                if (!name) { Dom.toast('请输入套组名称', 'error'); return; }
                await AjaxLoader.api('lab/panel-save', { method: 'POST', data: { name, item_ids: ids } });
                Dom.toast('套组已保存', 'success');
                const r = await AjaxLoader.api('lab/panel-list', { silent: true });
                LabPanel.panels = r.data || [];
                LabPanel.renderPanels();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    deletePanel(id) {
        Dom.modal('删除套组', '<p>删除该常用套组？</p>', [
            { text: '删除', type: 'danger', onclick: async function() {
                await AjaxLoader.api('lab/panel-delete', { method: 'POST', data: { id } });
                Dom.toast('已删除', 'success');
                LabPanel.panels = LabPanel.panels.filter(p => p.id != id);
                LabPanel.renderPanels();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    async submit(flag) {
        if (!this.selected.length) { Dom.toast('请先选择检验项目', 'error'); return; }
        const r = await AjaxLoader.api('lab/apply', { method: 'POST', data: {
            patient_id: this.patientId, item_ids: this.selected, submit: flag
        }});
        if (r.status === 'success') {
            Dom.toast(r.message, 'success');
            if (typeof outpatient_labs_loadApply === 'function') outpatient_labs_loadApply();
        }
    }
};
