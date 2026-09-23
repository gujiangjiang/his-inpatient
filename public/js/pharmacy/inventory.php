<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>药品库存</h2>
    <button class="btn btn-primary" onclick="pharmacy_inventory_new()">添加药品</button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr><th>编码</th><th>名称</th><th>规格</th><th>单位</th><th>价格</th><th>库存</th><th>操作</th></tr>
        </thead>
        <tbody id="med-table-body"></tbody>
    </table>
</div>

<script>
async function pharmacy_inventory_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_pharmacy.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    loadInventory();
}

async function loadInventory() {
    const result = await AjaxLoader.api('pharmacy/medication-list', {silent:true});
    const tbody = document.getElementById('med-table-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(m => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(m.code)}</td>
            <td>${Format.escape(m.name)}</td>
            <td>${Format.escape(m.specification||'')}</td>
            <td>${Format.escape(m.unit||'')}</td>
            <td>${m.price}</td>
            <td>${m.stock_quantity}</td>
            <td><button class="btn btn-small" onclick="pharmacy_inventory_edit(${m.id})">编辑</button></td>
        `;
        tbody.appendChild(tr);
    });
}

function pharmacy_inventory_new() {
    showMedForm({});
}
function pharmacy_inventory_edit(id) {
    AjaxLoader.api('pharmacy/medication-list', {silent:true}).then(r => {
        const med = r.data.find(m => m.id == id);
        if (med) showMedForm(med);
    });
}
function showMedForm(med) {
    const content = `
        <form id="med-form">
            ${Dom.formField({name:'code',label:'药品编码',type:'text'}, med.code||'')}
            ${Dom.formField({name:'name',label:'药品名称',type:'text'}, med.name||'')}
            ${Dom.formField({name:'specification',label:'规格',type:'text'}, med.specification||'')}
            ${Dom.formField({name:'unit',label:'单位',type:'text'}, med.unit||'')}
            ${Dom.formField({name:'price',label:'价格',type:'text'}, med.price||'')}
            ${Dom.formField({name:'stock_quantity',label:'库存',type:'number'}, med.stock_quantity||0)}
            <input type="hidden" name="id" value="${med.id||''}">
        </form>
    `;
    Dom.modal('药品信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('med-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            await AjaxLoader.api('pharmacy/medication-save', {method:'POST',data});
            loadInventory();
        }},
        {text:'取消',type:'default'}
    ]);
}
function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
