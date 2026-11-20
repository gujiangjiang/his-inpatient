<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">病区管理</h3>
        <p class="page-desc">病区与床位规模维护</p>
    </div>
    <button class="btn btn-primary" onclick="admin_wards_new()">添加病区</button>
</div>

<div class="stat-cards" id="ward-stats"></div>

<div class="card list-filter">
    <div class="filter-item">
        <label>归属科室</label>
        <select class="form-control" id="ward-filter-dept" onchange="loadWards()"><option value="">全部</option></select>
    </div>
    <div class="filter-item" style="flex:1;min-width:200px">
        <input class="form-control" id="ward-filter-kw" placeholder="搜索编码 / 名称" style="width:100%" oninput="wardDebounced()">
    </div>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>编码</th><th>名称</th><th>归属科室</th><th>楼层</th><th>床位数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="ward-table-body"></tbody>
    </table>
</div>

<script>
var _wardDepts = [];
var _wardAll = [];

async function admin_wards_init() {
    await App.mountShell('admin');
    const depts = await AjaxLoader.api('admin/department-list', { silent: true });
    _wardDepts = depts.data || [];
    const sel = document.getElementById('ward-filter-dept');
    _wardDepts.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.id; opt.textContent = d.name;
        sel.appendChild(opt);
    });
    loadWards();
}

function wardDebounced() {
    clearTimeout(_wardTimer);
    _wardTimer = setTimeout(loadWards, 250);
}

async function loadWards() {
    const result = await AjaxLoader.api('admin/ward-list', {silent:true});
    _wardAll = result.data || [];
    const dept = document.getElementById('ward-filter-dept').value;
    const kw = document.getElementById('ward-filter-kw').value.trim().toLowerCase();
    const list = _wardAll.filter(w => {
        if (dept && String(w.department_id) !== dept) return false;
        if (kw && !(w.code||'').toLowerCase().includes(kw) && !(w.name||'').toLowerCase().includes(kw)) return false;
        return true;
    });
    const tbody = document.getElementById('ward-table-body');
    tbody.innerHTML = '';
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="7"><div class="empty-tip">暂无匹配病区</div></td></tr>'; }
    list.forEach(w => {
        const tr = document.createElement('tr');
        const deptName = _wardDepts.find(d => d.id == w.department_id);
        tr.innerHTML = `
            <td class="fs-12 text-muted">${Format.escape(w.code)}</td>
            <td>${Format.escape(w.name)}</td>
            <td>${deptName ? Format.escape(deptName.name) : '<span class="text-muted">全部科室</span>'}</td>
            <td>${Format.escape(w.floor||'')}</td>
            <td><span class="mini-badge">${w.bed_count} 床</span></td>
            <td>${w.is_active ? '<span class="badge badge-success">激活</span>' : '<span class="badge badge-default">禁用</span>'}</td>
            <td><button class="btn btn-small" onclick="admin_wards_edit(${w.id})">编辑</button></td>
        `;
        tbody.appendChild(tr);
    });
    renderWardStats();
}

function renderWardStats() {
    const box = document.getElementById('ward-stats');
    const total = _wardAll.length;
    const active = _wardAll.filter(w => w.is_active).length;
    const beds = _wardAll.reduce((s, w) => s + Number(w.bed_count || 0), 0);
    box.innerHTML = [
        ['病区总数', total], ['激活', active], ['床位合计', beds]
    ].map(s => `<div class="stat-card"><div class="stat-num">${s[1]}</div><div class="stat-label">${s[0]}</div></div>`).join('');
}

function admin_wards_new() { showWardForm({}); }
async function admin_wards_edit(id) {
    const ward = _wardAll.find(w => w.id == id);
    if (ward) showWardForm(ward);
}

function showWardForm(ward) {
    const deptOpts = [{ label: '全部科室可用', value: '' }].concat(
        _wardDepts.map(d => ({ label: d.name, value: d.id }))
    );
    const content = `
        <form id="ward-form">
            <div class="form-grid">
            ${Dom.formField({name:'code',label:'编码',type:'text'}, ward.code||'')}
            ${Dom.formField({name:'name',label:'名称',type:'text'}, ward.name||'')}
            ${Dom.formField({name:'department_id',label:'归属科室',type:'select',options:deptOpts}, ward.department_id||'')}
            ${Dom.formField({name:'floor',label:'楼层',type:'text'}, ward.floor||'')}
            ${Dom.formField({name:'bed_count',label:'床位数',type:'number'}, ward.bed_count||0)}
            ${Dom.formField({name:'is_active',label:'状态',type:'select',options:[{label:'激活',value:'1'},{label:'禁用',value:'0'}]}, ward.is_active ? '1' : '0')}
            <input type="hidden" name="id" value="${ward.id||''}">
            </div>
        </form>
    `;
    Dom.modal('病区信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('ward-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            const r = await AjaxLoader.api('admin/ward-save', {method:'POST',data});
            if (r.status === 'success') { Dom.toast(r.message || '已保存', 'success'); loadWards(); }
            else Dom.toast(r.message || '保存失败', 'error');
        }},
        {text:'取消',type:'default'}
    ]);
}
</script>