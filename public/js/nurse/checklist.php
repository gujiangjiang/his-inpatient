<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>核查清单</h2>
    <button class="btn btn-success" onclick="nurse_checklist_refresh()">刷新</button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr><th>患者</th><th>床号</th><th>医嘱</th><th>科室</th><th>类型</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody id="checklist-body"></tbody>
    </table>
</div>

<script>
async function nurse_checklist_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_nurse.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    await loadChecklist();
}

async function loadChecklist() {
    const result = await AjaxLoader.api('nurse/order-list', {silent:true});
    const tbody = document.getElementById('checklist-body');
    tbody.innerHTML = '';
    const rows = result.data || [];
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">暂无待核查/待执行医嘱</div></td></tr>';
        return;
    }
    rows.forEach(o => {
        let act;
        if (o.status === 'invalid') {
            act = '<span class="fs-12 text-muted">已毁方作废</span>';
        } else if (o.status === 'pending') {
            act = `<button class="btn btn-small btn-success" onclick="nurse_checklist_verify(${o.id})">核查</button>`;
        } else if (o.category === 'medication' && !o.dispensed_id) {
            act = '<span class="fs-12 text-muted">待发药后执行</span>';
        } else {
            act = `<button class="btn btn-small btn-success" onclick="nurse_checklist_execute(${o.id})">执行</button>`;
        }
        const tr = document.createElement('tr');
        if (o.status === 'invalid') tr.className = 'os-txt-invalid';
        tr.innerHTML = `
            <td>${Format.escape(o.patient_name||'')}</td>
            <td>${Format.escape(o.bed_no||'')}</td>
            <td>${Format.escape(o.content||'')}</td>
            <td>${Format.escape(o.department_name||'')}</td>
            <td>${Format.escape(o.category === 'medication' ? '药品' : '医嘱')}</td>
            <td><span class="badge ${Format.orderStatusClass(o.status)}">${Format.orderStatus(o.status)}</span></td>
            <td>${act}</td>
        `;
        tbody.appendChild(tr);
    });
}

function nurse_checklist_refresh() {
    loadChecklist();
}

async function nurse_checklist_verify(orderId) {
    await AjaxLoader.api('orders/verify', {method:'POST',data:{id:orderId}});
    loadChecklist();
}

async function nurse_checklist_execute(orderId) {
    await AjaxLoader.api('nurse/nursing-action', {
        method:'POST',
        data:{order_id: orderId, action: 'execute'}
    });
    loadChecklist();
}

</script>
