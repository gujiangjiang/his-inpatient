<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">科室管理</h3>
        <p class="page-desc">临床科室信息与风险评估量表配置</p>
    </div>
    <button class="btn btn-primary" onclick="admin_departments_new()">添加科室</button>
</div>

<div class="stat-cards" id="dept-stats"></div>

<div class="card list-filter">
    <div class="filter-item">
        <label>状态</label>
        <select class="form-control" id="dept-filter-active" onchange="loadDepartments()">
            <option value="">全部</option>
            <option value="1">激活</option>
            <option value="0">禁用</option>
        </select>
    </div>
    <div class="filter-item" style="flex:1;min-width:200px">
        <input class="form-control" id="dept-filter-kw" placeholder="搜索编码 / 名称" style="width:100%" oninput="deptDebounced()">
    </div>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>编码</th><th>名称</th><th>描述</th><th>风险评估量表</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="dept-table-body"></tbody>
    </table>
</div>

<script>
var _deptAll = [];

async function admin_departments_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    loadDepartments();
}

var _RISK_LABELS = { caprini: 'Caprini (外科VTE)', padua: 'Padua (内科VTE)', cha2ds2: 'CHA₂DS₂-VASc', hasbled: 'HAS-BLED', none: '不启用' };

function deptDebounced() {
    clearTimeout(_deptTimer);
    _deptTimer = setTimeout(loadDepartments, 250);
}

async function loadDepartments() {
    const result = await AjaxLoader.api('admin/department-list', {silent:true});
    _deptAll = result.data || [];
    const active = document.getElementById('dept-filter-active').value;
    const kw = document.getElementById('dept-filter-kw').value.trim().toLowerCase();
    const list = _deptAll.filter(d => {
        if (active !== '' && String(d.is_active) !== active) return false;
        if (kw && !(d.code||'').toLowerCase().includes(kw) && !(d.name||'').toLowerCase().includes(kw)) return false;
        return true;
    });
    const tbody = document.getElementById('dept-table-body');
    tbody.innerHTML = '';
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="6"><div class="empty-tip">暂无匹配科室</div></td></tr>'; }
    list.forEach(d => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="fs-12 text-muted">${Format.escape(d.code)}</td>
            <td>${Format.escape(d.name)}</td>
            <td class="fs-13 text-muted">${Format.escape((d.description||'').slice(0, 60))}</td>
            <td><span class="mini-badge">${_RISK_LABELS[d.risk_assessment] || _RISK_LABELS.caprini}</span></td>
            <td>${d.is_active ? '<span class="badge badge-success">激活</span>' : '<span class="badge badge-default">禁用</span>'}</td>
            <td><button class="btn btn-small" onclick="admin_departments_edit(${d.id})">编辑</button></td>
        `;
        tbody.appendChild(tr);
    });
    renderDeptStats();
}

function renderDeptStats() {
    const box = document.getElementById('dept-stats');
    const total = _deptAll.length;
    const active = _deptAll.filter(d => d.is_active).length;
    const inactive = total - active;
    const riskCount = Object.values(_RISK_LABELS).length;
    box.innerHTML = [
        ['科室总数', total], ['激活', active], ['禁用', inactive], ['量表类型', riskCount]
    ].map(s => `<div class="stat-card"><div class="stat-num">${s[1]}</div><div class="stat-label">${s[0]}</div></div>`).join('');
}

function admin_departments_new() { showDeptForm({}); }
async function admin_departments_edit(id) {
    const dept = _deptAll.find(d => d.id == id);
    if (dept) showDeptForm(dept);
}

function showDeptForm(dept) {
    const riskOptions = Object.keys(_RISK_LABELS).map(v => ({ label: _RISK_LABELS[v], value: v }));
    const content = `
        <form id="dept-form">
            ${Dom.formField({name:'code',label:'编码',type:'text'}, dept.code||'')}
            ${Dom.formField({name:'name',label:'名称',type:'text'}, dept.name||'')}
            ${Dom.formField({name:'description',label:'描述',type:'textarea'}, dept.description||'')}
            ${Dom.formField({name:'risk_assessment',label:'风险评估量表',type:'select',options:riskOptions}, dept.risk_assessment||'caprini')}
            ${Dom.formField({name:'is_active',label:'状态',type:'select',options:[{label:'激活',value:'1'},{label:'禁用',value:'0'}]}, dept.is_active ? '1' : '0')}
            <input type="hidden" name="id" value="${dept.id||''}">
        </form>
    `;
    Dom.modal('科室信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('dept-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            const r = await AjaxLoader.api('admin/department-save', {method:'POST',data});
            if (r.status === 'success') { Dom.toast(r.message || '已保存', 'success'); loadDepartments(); }
            else Dom.toast(r.message || '保存失败', 'error');
        }},
        {text:'取消',type:'default'}
    ]);
}

</script>
