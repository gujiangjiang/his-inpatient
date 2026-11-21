<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">人员管理</h3>
        <p class="page-desc">账号、角色与科室/病区归属</p>
    </div>
    <button class="btn btn-primary" onclick="admin_users_new()">添加人员</button>
</div>

<div class="stat-cards" id="user-stats"></div>

<div class="card list-filter">
    <div class="filter-item">
        <label>角色</label>
        <select class="form-control" id="user-filter-role" onchange="admin_users_load()">
            <option value="">全部</option>
            <option value="admin">管理员</option>
            <option value="doctor">医生</option>
            <option value="nurse">护士</option>
            <option value="pharmacist">药师</option>
            <option value="lab_tech">检验技师</option>
        </select>
    </div>
    <div class="filter-item">
        <label>状态</label>
        <select class="form-control" id="user-filter-active" onchange="admin_users_load()">
            <option value="">全部</option>
            <option value="1">激活</option>
            <option value="0">禁用</option>
        </select>
    </div>
    <div class="filter-item" style="flex:1;min-width:200px">
        <input class="form-control" id="user-filter-kw" placeholder="搜索用户名 / 姓名" style="width:100%" oninput="admin_users_debounced()">
    </div>
    <div class="filter-item">
        <button class="btn btn-small" onclick="admin_users_load()">查询</button>
        <button class="btn btn-small" onclick="admin_users_reset_filter()">重置</button>
    </div>
</div>

<div class="card">
    <div class="card-header">账号列表 <span class="head-stat" id="user-count"></span></div>
    <table class="table">
        <thead><tr><th>用户名</th><th>姓名</th><th>角色</th><th>科室</th><th>病区</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody id="user-table-body"></tbody>
    </table>
    <div class="flex-between mt-10" style="padding:0 6px">
        <span class="text-muted fs-12">共 <b id="user-total">0</b> 条</span>
        <div>
            <button class="btn btn-small" id="user-prev" onclick="admin_users_page(-1)">上一页</button>
            <span class="text-muted fs-12" id="user-pageinfo"></span>
            <button class="btn btn-small" id="user-next" onclick="admin_users_page(1)">下一页</button>
        </div>
    </div>
</div>

<script>
var _deptOptions = [], _wardOptions = [];
var _userAll = [];
var _userPage = 1, _userPer = 15;

async function admin_users_init() {
    await App.mountShell('admin');
    const [depts, wards] = await Promise.all([
        AjaxLoader.api('admin/department-list', {silent:true}),
        AjaxLoader.api('admin/ward-list', {silent:true})
    ]);
    _deptOptions = depts.data || [];
    _wardOptions = wards.data || [];
    admin_users_load();
}

function admin_users_debounced() {
    clearTimeout(_userTimer);
    _userTimer = setTimeout(function () { _userPage = 1; admin_users_load(); }, 250);
}

function admin_users_reset_filter() {
    document.getElementById('user-filter-role').value = '';
    document.getElementById('user-filter-active').value = '';
    document.getElementById('user-filter-kw').value = '';
    _userPage = 1;
    admin_users_load();
}

function admin_users_page(d) {
    const pages = Math.max(1, Math.ceil(_userFiltered().length / _userPer));
    _userPage = Math.min(pages, Math.max(1, _userPage + d));
    admin_users_load();
}

function _userFiltered() {
    const role = document.getElementById('user-filter-role').value;
    const active = document.getElementById('user-filter-active').value;
    const kw = document.getElementById('user-filter-kw').value.trim().toLowerCase();
    return _userAll.filter(u => {
        if (role && u.role !== role) return false;
        if (active !== '' && String(u.is_active) !== active) return false;
        if (kw && !(u.username||'').toLowerCase().includes(kw) && !(u.name||'').toLowerCase().includes(kw)) return false;
        return true;
    });
}

async function admin_users_load() {
    /* SPA 竞态守卫: 搜索防抖定时器在切页后触发时, 面板元素已被替换出 DOM */
    if (!document.getElementById('user-table-body')) return;
    const r = await AjaxLoader.api('admin/user-list', {silent:true});
    _userAll = r.data || [];
    const filtered = _userFiltered();
    const pages = Math.max(1, Math.ceil(filtered.length / _userPer));
    if (_userPage > pages) _userPage = pages;
    const rows = filtered.slice((_userPage - 1) * _userPer, _userPage * _userPer);
    document.getElementById('user-total').textContent = filtered.length;
    document.getElementById('user-count').textContent = filtered.length + ' 条';
    document.getElementById('user-pageinfo').textContent = (_userPage) + ' / ' + pages;
    document.getElementById('user-prev').disabled = _userPage <= 1;
    document.getElementById('user-next').disabled = _userPage >= pages;

    const tbody = document.getElementById('user-table-body');
    tbody.innerHTML = '';
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8"><div class="empty-tip">暂无匹配人员</div></td></tr>'; }
    rows.forEach(u => {
        const dept = _deptOptions.find(d=>d.id==u.department_id);
        const ward = _wardOptions.find(w=>w.id==u.ward_id);
        const roleCls = { admin:'badge-danger', doctor:'badge-info', nurse:'badge-success', pharmacist:'badge-warning', lab_tech:'badge-default' }[u.role] || 'badge-default';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(u.username)}</td>
            <td>${Format.escape(u.name)}</td>
            <td><span class="badge ${roleCls}">${Format.role(u.role)}</span></td>
            <td>${dept ? Format.escape(dept.name) : ''}</td>
            <td>${ward ? Format.escape(ward.name) : ''}</td>
            <td>${u.is_active ? '<span class="badge badge-success">激活</span>' : '<span class="badge badge-default">禁用</span>'}</td>
            <td class="fs-12 text-muted">${u.created_at ? Format.datetime(u.created_at) : ''}</td>
            <td>
                <button class="btn btn-small" onclick="admin_users_edit(${u.id})">编辑</button>
                <button class="btn btn-small" onclick="admin_users_reset(${u.id})">重置密码</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    renderUserStats();
}

function renderUserStats() {
    const box = document.getElementById('user-stats');
    const stats = { admin:0, doctor:0, nurse:0, pharmacist:0, lab_tech:0, active:0, disabled:0 };
    _userAll.forEach(u => {
        if (stats[u.role] != null) stats[u.role]++;
        u.is_active ? stats.active++ : stats.disabled++;
    });
    box.innerHTML = [
        ['管理员', stats.admin], ['医生', stats.doctor], ['护士', stats.nurse],
        ['药师', stats.pharmacist], ['检验技师', stats.lab_tech],
        ['激活', stats.active], ['禁用', stats.disabled]
    ].map(s => `<div class="stat-card"><div class="stat-num">${s[1]}</div><div class="stat-label">${s[0]}</div></div>`).join('');
}

function admin_users_new() { showUserForm({}); }
async function admin_users_edit(id) {
    const user = _userAll.find(u => u.id == id);
    if (user) showUserForm(user);
}

function admin_users_reset(id) {
    Dom.modal('重置密码', '<p>重置密码为 123456，请及时修改</p>', [
        {text:'确认',type:'danger',onclick:async function(){
            await AjaxLoader.api('admin/user-save', {method:'POST',data:{id:id, password:'123456'}});
            Dom.toast('已重置为 123456', 'success');
            admin_users_load();
        }},
        {text:'取消',type:'default'}
    ]);
}

function showUserForm(user) {
    let deptHtml = '<option value="">请选择</option>';
    _deptOptions.forEach(d => { deptHtml += `<option value="${d.id}" ${user.department_id==d.id?'selected':''}>${Format.escape(d.name)}</option>`; });
    let wardHtml = '<option value="">请选择</option>';
    _wardOptions.forEach(w => { wardHtml += `<option value="${w.id}" ${user.ward_id==w.id?'selected':''}>${Format.escape(w.name)}</option>`; });

    const content = `
        <form id="user-form">
            ${Dom.formField({name:'username',label:'用户名',type:'text'}, user.username||'')}
            ${Dom.formField({name:'name',label:'姓名',type:'text'}, user.name||'')}
            ${Dom.formField({name:'password',label:'密码(留空不修改)',type:'password'}, '')}
            ${Dom.formField({name:'role',label:'角色',type:'select',options:[
                {label:'管理员',value:'admin'},
                {label:'医生',value:'doctor'},
                {label:'护士',value:'nurse'},
                {label:'药师',value:'pharmacist'},
                {label:'检验技师',value:'lab_tech'}
            ]}, user.role||'')}
            <div class="form-group"><label class="form-label">科室</label><select name="department_id" class="form-control">${deptHtml}</select></div>
            <div class="form-group"><label class="form-label">病区</label><select name="ward_id" class="form-control">${wardHtml}</select></div>
            ${Dom.formField({name:'is_active',label:'状态',type:'select',options:[{label:'激活',value:'1'},{label:'禁用',value:'0'}]}, user.is_active ? '1' : '0')}
            <input type="hidden" name="id" value="${user.id||''}">
        </form>
    `;
    Dom.modal('人员信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('user-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            const r = await AjaxLoader.api('admin/user-save', {method:'POST',data});
            if (r.status === 'success') { Dom.toast(r.message || '已保存', 'success'); admin_users_load(); }
            else Dom.toast(r.message || '保存失败', 'error');
        }},
        {text:'取消',type:'default'}
    ]);
}
</script>