<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>中心药房摆药</h2>
    <div>
        <button class="btn btn-small" onclick="pharmacy_dispensing_scope('all')">全部</button>
        <button class="btn btn-small" onclick="pharmacy_dispensing_scope('prepared')">仅已预摆</button>
        <button class="btn btn-success" onclick="pharmacy_dispensing_refresh()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">待摆药队列（已核对，摆药即扣费；预摆药由护士站申请）</div>
    <table class="table">
        <thead>
            <tr><th><input type="checkbox" id="dispense-check-all"></th><th>患者</th><th>床号/病区</th><th>医嘱</th><th>频次</th><th>预摆</th><th>自动算量</th><th>金额</th><th>操作</th></tr>
        </thead>
        <tbody id="dispensing-queue-body"></tbody>
    </table>
    <div class="flex-between mt-10">
        <button class="btn btn-primary" onclick="pharmacy_dispense_selected()">批量摆药扣费</button>
        <span class="text-muted" id="dispensing-summary"></span>
    </div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">发药记录（已摆药可送达病区）</div>
    <table class="table">
        <thead><tr><th>患者</th><th>药品</th><th>数量</th><th>金额</th><th>状态</th><th>发药人</th><th>时间</th><th>操作</th></tr></thead>
        <tbody id="dispensing-records-body"></tbody>
    </table>
</div>

<script>
var _dispensingScope = 'all';
var _dispensingQueue = [];

async function pharmacy_dispensing_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_pharmacy.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    document.getElementById('dispense-check-all').addEventListener('change', function () {
        document.querySelectorAll('.dispense-row-check').forEach(c => c.checked = this.checked);
    });
    await loadQueue();
    await loadRecords();
}

function pharmacy_dispensing_scope(s) {
    _dispensingScope = s;
    loadQueue();
}

async function loadQueue() {
    const result = await AjaxLoader.api('pharmacy/dispense2?action=queue&scope=' + _dispensingScope, { silent: true });
    _dispensingQueue = result.data || [];
    const tbody = document.getElementById('dispensing-queue-body');
    tbody.innerHTML = '';
    (_dispensingQueue).forEach(o => {
        const pre = o.dispense_status === 'prepared'
            ? '<span class="badge badge-warning">已预摆</span>' + (o.plan_time ? ' ' + Format.escape(o.plan_time) : '')
            : '<span class="badge badge-default">未预摆</span>';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="checkbox" class="dispense-row-check" value="${o.id}"></td>
            <td>${Format.escape(o.patient_name||'')}</td>
            <td>${Format.escape(o.bed_no||'')} / ${Format.escape(o.ward_name||'')}</td>
            <td>${Format.escape(o.content||'')}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}</td>
            <td>${Format.escape(o.freq_code||'')}</td>
            <td>${pre}</td>
            <td>${o.auto_qty} ${Format.escape(o.spec_pack_unit||'')}</td>
            <td>¥${Number(o.auto_amount||0).toFixed(2)}</td>
            <td>
                <button class="btn btn-small btn-success" onclick="pharmacy_dispense_one(${o.id})">摆药</button>
                ${o.dispense_status === 'prepared' ? '<button class="btn btn-small" onclick="pharmacy_cancel_prepare(${o.id})">取消预摆</button>' : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
    const totalAmt = _dispensingQueue.reduce((s, o) => s + Number(o.auto_amount || 0), 0);
    document.getElementById('dispensing-summary').textContent = '队列 ' + _dispensingQueue.length + ' 条，预估扣费 ¥' + totalAmt.toFixed(2);
}

async function pharmacy_dispense_one(id) {
    const r = await AjaxLoader.api('pharmacy/dispense2', { method: 'POST', data: { action: 'dispense', ids: [id] } });
    Dom.toast(r.message || '已处理', r.data && r.data.fail && r.data.fail.length ? 'error' : 'success');
    loadQueue(); loadRecords();
}

async function pharmacy_dispense_selected() {
    const ids = [...document.querySelectorAll('.dispense-row-check:checked')].map(c => Number(c.value));
    if (!ids.length) { Dom.toast('请先勾选要摆药的医嘱', 'error'); return; }
    const r = await AjaxLoader.api('pharmacy/dispense2', { method: 'POST', data: { action: 'dispense', ids } });
    Dom.toast(r.message || '已处理', r.data && r.data.fail && r.data.fail.length ? 'error' : 'success');
    loadQueue(); loadRecords();
}

async function pharmacy_cancel_prepare(id) {
    await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'cancel', id } });
    Dom.toast('已取消预摆', 'success');
    loadQueue();
}

async function loadRecords() {
    const result = await AjaxLoader.api('pharmacy/dispensing-list', { silent: true });
    const tbody = document.getElementById('dispensing-records-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(r => {
        const canDeliver = r.status === 'dispensed' && r.stage !== 'delivered';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(r.patient_name||'')}</td>
            <td>${Format.escape(r.med_name||'')}</td>
            <td>${r.quantity}</td>
            <td>¥${Number(r.amount||0).toFixed(2)}</td>
            <td>${r.stage === 'delivered' ? '<span class="badge badge-success">已送达</span>' : (r.status === 'dispensed' ? '<span class="badge badge-warning">已发药</span>' : Format.escape(r.status))}</td>
            <td>${Format.escape(r.dispenser_name||'')}</td>
            <td>${Format.datetime(r.dispensed_at)}</td>
            <td>${canDeliver ? `<button class="btn btn-small btn-primary" onclick="pharmacy_deliver(${r.id})">送达</button>` : ''}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function pharmacy_deliver(recId) {
    const r = await AjaxLoader.api('pharmacy/dispense2', { method: 'POST', data: { action: 'deliver', id: recId } });
    if (r.status === 'success') Dom.toast(r.message || '已送达', 'success');
    else Dom.toast(r.message || '送达失败', 'error');
    loadRecords();
}

function pharmacy_dispensing_refresh() { loadQueue(); loadRecords(); }
</script>
