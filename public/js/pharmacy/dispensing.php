<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>发药管理</h2>
    <button class="btn btn-success" onclick="pharmacy_dispensing_refresh()">刷新</button>
</div>

<div class="card">
    <div class="card-header">待发药队列</div>
    <table class="table">
        <thead>
            <tr><th>患者</th><th>床号</th><th>病区</th><th>医嘱内容</th><th>开嘱医生</th><th>操作</th></tr>
        </thead>
        <tbody id="dispensing-queue-body"></tbody>
    </table>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">发药记录</div>
    <table class="table">
        <thead>
            <tr><th>患者</th><th>药品</th><th>数量</th><th>发药人</th><th>状态</th><th>时间</th></tr>
        </thead>
        <tbody id="dispensing-records-body"></tbody>
    </table>
</div>

<script>
async function pharmacy_dispensing_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_pharmacy.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    await loadQueue();
    await loadRecords();
}

async function loadQueue() {
    const result = await AjaxLoader.api('pharmacy/pending-queue', {silent:true});
    const tbody = document.getElementById('dispensing-queue-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(o => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(o.patient_name||'')}</td>
            <td>${Format.escape(o.bed_no||'')}</td>
            <td>${Format.escape(o.ward_name||'')}</td>
            <td>${Format.escape(o.content||'')}</td>
            <td>${Format.escape(o.doctor_name||'')}</td>
            <td>
                <button class="btn btn-small btn-success" onclick="pharmacy_dispense(${o.id})">发药</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadRecords() {
    const result = await AjaxLoader.api('pharmacy/dispensing-list', {silent:true});
    const tbody = document.getElementById('dispensing-records-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(r.patient_name||'')}</td>
            <td>${Format.escape(r.med_name||'')}</td>
            <td>${r.quantity}</td>
            <td>${Format.escape(r.dispenser_name||'')}</td>
            <td>${r.status}</td>
            <td>${Format.datetime(r.dispensed_at)}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function pharmacy_dispense(orderId) {
    // 获取药品列表
    const medsResult = await AjaxLoader.api('pharmacy/medication-list', {silent:true});
    const meds = medsResult.data || [];

    let options = '<option value="">选择药品</option>';
    meds.forEach(m => {
        options += `<option value="${m.id}" data-stock="${m.stock_quantity}" data-name="${Format.escape(m.name)}">${Format.escape(m.name)} (库存: ${m.stock_quantity})</option>`;
    });

    const content = `
        <div class="form-group">
            <label class="form-label">药品</label>
            <select id="med-select" class="form-control">${options}</select>
        </div>
        <div class="form-group">
            <label class="form-label">数量</label>
            <input type="number" id="med-quantity" class="form-control" min="1" value="1">
        </div>
        <div id="med-stock-info"></div>
    `;
    const modal = Dom.modal('发药', content, [
        {text:'确认',type:'primary',onclick:async function(){
            const sel = document.getElementById('med-select');
            const medId = sel.value;
            const qty = parseInt(document.getElementById('med-quantity').value);
            if (!medId) { Dom.toast('请选择药品','error'); return; }
            if (qty <= 0) { Dom.toast('请输入有效数量','error'); return; }
            const stock = parseInt(sel.options[sel.selectedIndex].dataset.stock);
            if (qty > stock) { Dom.toast('库存不足（当前 ' + stock + '）','error'); return; }
            const result = await AjaxLoader.api('pharmacy/dispensing-save', {
                method:'POST',
                data:{order_id: orderId, medication_id: medId, quantity: qty}
            });
            if (result.status === 'success') {
                Dom.toast('发药成功，剩余库存: ' + result.data.new_stock, 'success');
            }
        }},
        {text:'取消',type:'default'}
    ]);

    // 监听药品选择变化
    document.getElementById('med-select').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const stock = opt.dataset.stock;
        const name = opt.dataset.name;
        document.getElementById('med-stock-info').innerHTML = `<small>药品: ${name} | 当前库存: ${stock}</small>`;
    });
}

function pharmacy_dispensing_refresh() {
    loadQueue();
    loadRecords();
}

function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
