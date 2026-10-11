<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">处方</h3>
        <p class="page-desc">开具处方（药品项目库来自门诊一体化）与已开处方管理</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="RxPanel.open()">新方（F9）</button>
        <button class="btn" onclick="clinic_rx_load()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">已开处方 <span class="fs-12 text-muted">编辑请前往医嘱工作站；参考金额按项目库价格估算</span></div>
    <div id="rx-notice"></div>
    <div style="overflow-x:auto">
    <table class="table" id="rx-table">
        <thead><tr><th>次</th><th>处方</th><th>项数</th><th>状态</th><th>开单时间</th><th>参考金额</th><th>操作</th></tr></thead>
        <tbody id="rx-body"></tbody>
    </table>
    </div>
</div>

<script>
var _rxPriceMap = {};
var _rxSource = 'local';

async function clinic_rx_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    clinic_rx_load();
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F9' && window.location.hash === '#clinic/rx') {
            e.preventDefault();
            RxPanel.open();
        }
    });
}

function clinic_rx_load() {
    const p = PatientContext.current();
    if (!p) return;
    Promise.all([
        AjaxLoader.api('orders/list?patient_id=' + p.id + '&per_page=500', { silent: true }),
        AjaxLoader.api('rx/catalog', { silent: true })
    ]).then(([or, cr]) => {
        _rxSource = cr.source || 'local';
        _rxPriceMap = {};
        (cr.data || []).forEach(d => { _rxPriceMap[d.name] = Number(d.price || 0); });
        const roots = (or.data || []).filter(o => o.category === 'medication' || o.order_type === 'medication');
        const notice = document.getElementById('rx-notice');
        if (!roots.length) {
            notice.innerHTML = '<div class="empty-state">暂无处方，点击右上角「新方」开具</div>';
            document.getElementById('rx-body').innerHTML = '';
            return;
        }
        notice.innerHTML = '';
        document.getElementById('rx-body').innerHTML = roots.map((o, i) => {
            const subs = o.sub_orders || [];
            const items = [o].concat(subs);
            const title = subs.length ? (o.content + ' 等 ' + items.length + ' 项') : o.content;
            let total = 0;
            items.forEach(it => { total += (_rxPriceMap[it.content] || 0); });
            const del = o.status === 'draft'
                ? `<button class="btn btn-small btn-danger" onclick="clinic_rx_delete(${o.id})">删除</button>` : '';
            return `<tr>
                <td>${i + 1}</td>
                <td>${Format.escape(title)}</td>
                <td>${items.length}</td>
                <td><span class="badge ${Format.orderStatusClass(o.status)}">${Format.orderStatus(o.status)}</span></td>
                <td>${Format.escape(o.start_date || o.created_at || '')}</td>
                <td>¥${total.toFixed(2)}</td>
                <td>${del}</td>
            </tr>`;
        }).join('');
    }).catch(() => {});
}

function clinic_rx_delete(rootId) {
    const p = PatientContext.current();
    Dom.modal('删除处方', '<p>删除该处方（整组草稿医嘱）？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            const or = await AjaxLoader.api('orders/list?patient_id=' + p.id + '&per_page=500', { silent: true });
            const root = (or.data || []).find(o => o.id == rootId);
            if (!root) return;
            const ids = [root.id].concat((root.sub_orders || []).map(s => s.id));
            await AjaxLoader.api('orders/batch-delete', { method: 'POST', data: { ids: ids } });
            Dom.toast('已删除', 'success');
            clinic_rx_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}

/* ===== 开方面板 ===== */
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
        const c = await AjaxLoader.api('rx/catalog', { silent: true });
        this.catalog = c.data || [];
        this.source = c.source || 'local';
        Dom.modal('新方（处方开具）', this.html(), [
            { text: '保存', type: 'primary', onclick: () => this.submit(0) },
            { text: '保存并提交', type: 'success', onclick: () => this.submit(1) },
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
                <input class="form-control" id="rx-kw" placeholder="搜索药品名称 / 规格 / 厂家（F9）" oninput="RxPanel.onKw(this.value)">
                <div class="apply-source">${this.source === 'opd' ? '项目库来源：门诊一体化系统（审核通过项，实时同步）' : '项目库来源：本地药品目录（门诊接口未启用/不可用）'}</div>
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

    onKw(v) { this.kw = v.trim(); this.renderList(); },

    onCat(el) {
        document.querySelectorAll('#rx-cats .apply-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        this.cat = el.dataset.cat;
        this.renderList();
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

    async submit(flag) {
        if (!this.selected.length) { Dom.toast('请先选择药品', 'error'); return; }
        const items = this.selected.map(id => {
            const i = this.itemById(id);
            return {
                content: i.name,
                dosage: i.single_dose || '',
                usage_name: i.route || '',
                freq_code: i.frequency_code || '',
                category: 'medication',
                order_period: 'long'
            };
        });
        const r = await AjaxLoader.api('orders/save', { method: 'POST', data: {
            patient_id: this.patientId,
            order_period: 'long',
            category: 'medication',
            items: items,
            submit: flag
        }});
        if (r.status === 'success') {
            Dom.toast('处方已生成（以成组医嘱形式保存）', 'success');
            clinic_rx_load();
        }
    }
};
</script>
