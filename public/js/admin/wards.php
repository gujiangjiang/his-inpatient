<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>病区管理</h2>
    <button class="btn btn-primary" onclick="admin_wards_new()">添加病区</button>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>编码</th><th>名称</th><th>楼层</th><th>床位数</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="ward-table-body"></tbody>
    </table>
</div>

<script>
async function admin_wards_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    loadWards();
}

async function loadWards() {
    const result = await AjaxLoader.api('admin/ward-list', {silent:true});
    const tbody = document.getElementById('ward-table-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(w => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(w.code)}</td>
            <td>${Format.escape(w.name)}</td>
            <td>${Format.escape(w.floor||'')}</td>
            <td>${w.bed_count}</td>
            <td>${w.is_active ? '激活' : '禁用'}</td>
            <td><button class="btn btn-small" onclick="admin_wards_edit(${w.id})">编辑</button></td>
        `;
        tbody.appendChild(tr);
    });
}

function admin_wards_new() { showWardForm({}); }
async function admin_wards_edit(id) {
    const result = await AjaxLoader.api('admin/ward-list', {silent:true});
    const ward = result.data.find(w => w.id == id);
    if (ward) showWardForm(ward);
}

function showWardForm(ward) {
    const content = `
        <form id="ward-form">
            ${Dom.formField({name:'code',label:'编码',type:'text'}, ward.code||'')}
            ${Dom.formField({name:'name',label:'名称',type:'text'}, ward.name||'')}
            ${Dom.formField({name:'floor',label:'楼层',type:'text'}, ward.floor||'')}
            ${Dom.formField({name:'bed_count',label:'床位数',type:'number'}, ward.bed_count||0)}
            ${Dom.formField({name:'is_active',label:'状态',type:'select',options:[{label:'激活',value:'1'},{label:'禁用',value:'0'}]}, ward.is_active ? '1' : '0')}
            <input type="hidden" name="id" value="${ward.id||''}">
        </form>
    `;
    Dom.modal('病区信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('ward-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            await AjaxLoader.api('admin/ward-save', {method:'POST',data});
            loadWards();
        }},
        {text:'取消',type:'default'}
    ]);
}
function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
