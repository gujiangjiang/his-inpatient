<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>科室管理</h2>
    <button class="btn btn-primary" onclick="admin_departments_new()">添加科室</button>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>编码</th><th>名称</th><th>描述</th><th>状态</th><th>操作</th></tr></thead>
        <tbody id="dept-table-body"></tbody>
    </table>
</div>

<script>
async function admin_departments_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    loadDepartments();
}

async function loadDepartments() {
    const result = await AjaxLoader.api('admin/department-list', {silent:true});
    const tbody = document.getElementById('dept-table-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(d => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(d.code)}</td>
            <td>${Format.escape(d.name)}</td>
            <td>${Format.escape(d.description||'')}</td>
            <td>${d.is_active ? '激活' : '禁用'}</td>
            <td><button class="btn btn-small" onclick="admin_departments_edit(${d.id})">编辑</button></td>
        `;
        tbody.appendChild(tr);
    });
}

function admin_departments_new() { showDeptForm({}); }
async function admin_departments_edit(id) {
    const result = await AjaxLoader.api('admin/department-list', {silent:true});
    const dept = result.data.find(d => d.id == id);
    if (dept) showDeptForm(dept);
}

function showDeptForm(dept) {
    const content = `
        <form id="dept-form">
            ${Dom.formField({name:'code',label:'编码',type:'text'}, dept.code||'')}
            ${Dom.formField({name:'name',label:'名称',type:'text'}, dept.name||'')}
            ${Dom.formField({name:'description',label:'描述',type:'textarea'}, dept.description||'')}
            ${Dom.formField({name:'is_active',label:'状态',type:'select',options:[{label:'激活',value:'1'},{label:'禁用',value:'0'}]}, dept.is_active ? '1' : '0')}
            <input type="hidden" name="id" value="${dept.id||''}">
        </form>
    `;
    Dom.modal('科室信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('dept-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            await AjaxLoader.api('admin/department-save', {method:'POST',data});
            loadDepartments();
        }},
        {text:'取消',type:'default'}
    ]);
}

function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
