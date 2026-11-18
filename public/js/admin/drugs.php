<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">药品管理</h3>
        <p class="page-desc">中心药房（可维护）· 门诊药房（接入 HIS 接口后只读显示）</p>
    </div>
    <div>
        <button class="btn btn-small" id="drug-tab-center" onclick="admin_drugs_type(0)">中心药房</button>
        <button class="btn btn-small" id="drug-tab-outpatient" onclick="admin_drugs_type(1)">门诊药房</button>
        <button class="btn btn-primary" id="drug-new-btn" onclick="admin_drugs_new()">新增药品</button>
        <button class="btn btn-success" onclick="admin_drugs_load()">刷新</button>
    </div>
</div>

<div class="stat-cards" id="drug-stats"></div>

<div class="card">
    <div class="card-header" id="drug-source-bar">加载中…</div>
    <div class="flex-between mb-10">
        <input class="form-control" id="drug-kw" placeholder="搜索名称 / 规格 / 分类" style="max-width:300px" oninput="admin_drugs_debounced()">
        <span class="text-muted" id="drug-count"></span>
    </div>
    <table class="table">
        <thead><tr><th>编码</th><th>名称</th><th>分类</th><th>规格</th><th>单位</th><th>单价</th><th>库存</th><th>警戒</th><th>拆零</th><th>操作</th></tr></thead>
        <tbody id="drug-body"></tbody>
    </table>
</div>
<script>
var _drugType = 'center';
var _drugPage = 1;
var _drugRows = [];
var _drugTimer = null;

async function admin_drugs_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    await admin_drugs_load();
}

function admin_drugs_type(idx) {
    _drugType = idx === 1 ? 'outpatient' : 'center';
    _drugPage = 1;
    document.getElementById('drug-tab-center').classList.toggle('btn-primary', idx !== 1);
    document.getElementById('drug-tab-outpatient').classList.toggle('btn-primary', idx === 1);
    document.getElementById('drug-new-btn').style.display = idx === 1 ? 'none' : '';
    admin_drugs_load();
}

function admin_drugs_debounced() {
    clearTimeout(_drugTimer);
    _drugTimer = setTimeout(admin_drugs_load, 250);
}

async function admin_drugs_load() {
    const kw = document.getElementById('drug-kw').value.trim();
    const r = await AjaxLoader.api('admin/drugs?action=list&type=' + _drugType + '&kw=' + encodeURIComponent(kw) + '&page=' + _drugPage, { silent: true });
    const data = r.data || {};
    _drugRows = data.rows || [];
    document.getElementById('drug-count').textContent = '共 ' + (data.total || 0) + ' 条';
    const bar = document.getElementById('drug-source-bar');
    if (_drugType === 'outpatient') {
        bar.textContent = data.opd_enabled
            ? (data.msg ? '门诊药房（' + data.msg + '）' : '门诊药房（实时库存，只读）')
            : '门诊药房（未启用门诊接口，无数据）';
    } else {
        bar.textContent = '中心药房（本地库存，可维护）';
    }
    const tbody = document.getElementById('drug-body');
    tbody.innerHTML = '';
    _drugRows.forEach(d => {
        const stockTxt = d.stock + (d.spec_pack_unit ? ' ' + Format.escape(d.spec_pack_unit) : '');
        const ops = d.readonly
            ? '<span class="text-muted">只读</span>'
            : '<button class="btn btn-small" onclick="admin_drugs_edit(' + d.id + ')">编辑</button>' +
              '<button class="btn btn-small btn-danger" onclick="admin_drugs_toggle(' + d.id + ',' + (d.is_active == 0 ? 1 : 0) + ')">' + (d.is_active == 0 ? '启用' : '停用') + '</button>';
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + Format.escape(d.code || '-') + '</td>' +
            '<td>' + Format.escape(d.name) + '</td>' +
            '<td>' + Format.escape(d.category || '') + '</td>' +
            '<td>' + Format.escape(d.spec || '') + '</td>' +
            '<td>' + Format.escape(d.unit || '') + '</td>' +
            '<td>¥' + Number(d.price || 0).toFixed(2) + '</td>' +
            '<td>' + stockTxt + '</td>' +
            '<td>' + (d.warn_qty ? d.warn_qty : '-') + '</td>' +
            '<td>' + (d.allow_split ? '<span class="badge badge-success">可拆零</span>' : '<span class="badge badge-default">整包装</span>') + '</td>' +
            '<td>' + ops + '</td>';
        tbody.appendChild(tr);
    });
    if (!_drugRows.length) tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state">暂无药品</div></td></tr>';
    renderDrugStats(data);
}

function renderDrugStats(data) {
    const box = document.getElementById('drug-stats');
    if (!box) return;
    const rows = _drugRows;
    const total = data.total || 0;
    const low = rows.filter(d => d.warn_qty > 0 && d.stock <= d.warn_qty).length;
    const stock = rows.reduce((s, d) => s + Number(d.stock || 0), 0);
    const cats = new Set(rows.map(d => d.category).filter(Boolean)).size;
    if (_drugType === 'center') {
        box.innerHTML = [
            ['药品总数', total], ['库存合计', stock], ['低库存', low], ['分类数', cats]
        ].map(s => `<div class="stat-card"><div class="stat-num">${s[1]}</div><div class="stat-label">${s[0]}</div></div>`).join('');
    } else {
        box.innerHTML = [
            ['门诊药品', total], ['库存合计', stock], ['低库存', low]
        ].map(s => `<div class="stat-card"><div class="stat-num">${s[1]}</div><div class="stat-label">${s[0]}</div></div>`).join('');
    }
}

function admin_drugs_new() { admin_drugs_form({}); }

function admin_drugs_edit(id) {
    const d = _drugRows.find(x => x.id == id);
    if (d) admin_drugs_form(d);
}

function admin_drugs_form(d) {
    d = d || {};
    const content = '<input type="hidden" id="dform-id" value="' + (d.id || '') + '">' +
        '<div class="form-group"><label class="form-label">药品名称 *</label>' +
        '<input class="form-control" id="dform-name" value="' + Format.escape(d.name || '') + '"></div>' +
        '<div class="form-grid">' +
        '<div class="form-group"><label class="form-label">分类</label><input class="form-control" id="dform-cat" value="' + Format.escape(d.category || '西药') + '"></div>' +
        '<div class="form-group"><label class="form-label">剂型</label><input class="form-control" id="dform-form" value="' + Format.escape(d.form || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">规格</label><input class="form-control" id="dform-spec" value="' + Format.escape(d.spec || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">包装单位</label><input class="form-control" id="dform-unit" value="' + Format.escape(d.unit || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">每包装数量</label><input class="form-control" id="dform-packqty" type="number" min="1" value="' + (d.spec_pack_qty || 1) + '"></div>' +
        '<div class="form-group"><label class="form-label">最小单位</label><input class="form-control" id="dform-packunit" value="' + Format.escape(d.spec_pack_unit || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">单价（包装价）</label><input class="form-control" id="dform-price" type="number" step="0.01" min="0" value="' + (d.price || 0) + '"></div>' +
        '<div class="form-group"><label class="form-label">库存（最小单位）</label><input class="form-control" id="dform-stock" type="number" min="0" value="' + (d.stock || 0) + '"></div>' +
        '<div class="form-group"><label class="form-label">警戒库存</label><input class="form-control" id="dform-warn" type="number" min="0" value="' + (d.warn_qty || 0) + '"></div>' +
        '<div class="form-group"><label class="form-label">默认单次剂量</label><input class="form-control" id="dform-dose" value="' + Format.escape(d.single_dose || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">默认频次</label><input class="form-control" id="dform-freq" value="' + Format.escape(d.frequency || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">默认用法</label><input class="form-control" id="dform-route" value="' + Format.escape(d.route || '') + '"></div>' +
        '</div>' +
        '<div class="form-group"><label class="form-checkbox"><input type="checkbox" id="dform-split" ' + (d.allow_split ? 'checked' : '') + '> 允许拆零销售</label></div>';
    Dom.modal(d.id ? '编辑药品' : '新增药品', content, [
        { text: '保存', type: 'primary', onclick: async function () {
            const data = {
                id: document.getElementById('dform-id').value || 0,
                name: document.getElementById('dform-name').value.trim(),
                category: document.getElementById('dform-cat').value.trim(),
                form: document.getElementById('dform-form').value.trim(),
                spec: document.getElementById('dform-spec').value.trim(),
                unit: document.getElementById('dform-unit').value.trim(),
                spec_pack_qty: document.getElementById('dform-packqty').value,
                spec_pack_unit: document.getElementById('dform-packunit').value.trim(),
                price: document.getElementById('dform-price').value,
                stock: document.getElementById('dform-stock').value,
                warn_qty: document.getElementById('dform-warn').value,
                single_dose: document.getElementById('dform-dose').value.trim(),
                frequency: document.getElementById('dform-freq').value.trim(),
                route: document.getElementById('dform-route').value.trim(),
                allow_split: document.getElementById('dform-split').checked ? 1 : 0,
            };
            const r = await AjaxLoader.api('admin/drugs', { method: 'POST', data: { action: 'save', ...data } });
            if (r.status === 'success') { Dom.toast(r.message || '已保存', 'success'); admin_drugs_load(); }
            else Dom.toast(r.message || '保存失败', 'error');
        } },
        { text: '取消', type: 'default' }
    ]);
}

async function admin_drugs_toggle(id, on) {
    const r = await AjaxLoader.api('admin/drugs', { method: 'POST', data: { action: 'toggle', id, on } });
    if (r.status === 'success') Dom.toast(r.message || (on ? '已启用' : '已停用'), 'success');
    else Dom.toast(r.message || '操作失败', 'error');
    admin_drugs_load();
}
</script>
