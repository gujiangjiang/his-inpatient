<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>预摆药</h2>
    <button class="btn btn-success" onclick="nurse_prepare_refresh()">刷新</button>
</div>

<div class="card">
    <div class="card-header">已核对待摆药药品医嘱（申请后中心药房优先摆药；勾选可批量）</div>
    <table class="table">
        <thead>
            <tr><th><input type="checkbox" id="nprep-check-all"></th><th>患者</th><th>床号/病区</th><th>医嘱</th><th>频次</th><th>预摆状态</th><th>计划时间</th><th>操作</th></tr>
        </thead>
        <tbody id="nprep-body"></tbody>
    </table>
    <div class="flex-between mt-10">
        <button class="btn btn-primary" onclick="nurse_prepare_apply()">申请预摆药（勾选）</button>
        <span class="text-muted">计划时间：<input type="datetime-local" id="nprep-plan" style="padding:2px 6px;border:1px solid var(--border);border-radius:6px"></span>
    </div>
</div>

<script>
var _nprepList = [];

async function nurse_prepare_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebar = await fetch('/js/shared/sidebar_nurse.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    document.getElementById('nprep-check-all').addEventListener('change', function () {
        document.querySelectorAll('.nprep-row-check').forEach(c => c.checked = this.checked);
    });
    await loadPrepared();
}

async function loadPrepared() {
    const result = await AjaxLoader.api('nurse/prepare?action=list', { silent: true });
    _nprepList = result.data || [];
    const tbody = document.getElementById('nprep-body');
    tbody.innerHTML = '';
    _nprepList.forEach(o => {
        const pre = o.dispense_status === 'prepared'
            ? '<span class="badge badge-warning">已预摆</span>'
            : '<span class="badge badge-default">未预摆</span>';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="checkbox" class="nprep-row-check" value="${o.id}" ${o.dispense_status === 'prepared' ? 'disabled' : ''}></td>
            <td>${Format.escape(o.patient_name||'')}</td>
            <td>${Format.escape(o.bed_no||'')} / ${Format.escape(o.ward_name||'')}</td>
            <td>${Format.escape(o.content||'')}${o.dosage ? ' ' + Format.escape(o.dosage) : ''}</td>
            <td>${Format.escape(o.freq_code||'')}</td>
            <td>${pre}</td>
            <td>${o.plan_time ? Format.escape(o.plan_time) : ''}</td>
            <td>${o.dispense_status === 'prepared'
                ? '<button class="btn btn-small" onclick="nurse_prepare_cancel(' + o.id + ')">取消</button>'
                : '<button class="btn btn-small btn-primary" onclick="nurse_prepare_one(' + o.id + ')">申请</button>'}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function nurse_prepare_one(id) {
    await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids: [id], plan_time: document.getElementById('nprep-plan').value } });
    Dom.toast('已申请预摆药', 'success');
    loadPrepared();
}

async function nurse_prepare_apply() {
    const ids = [...document.querySelectorAll('.nprep-row-check:checked')].map(c => Number(c.value));
    if (!ids.length) { Dom.toast('请先勾选要预摆药的医嘱', 'error'); return; }
    await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'apply', ids, plan_time: document.getElementById('nprep-plan').value } });
    Dom.toast('已申请预摆药', 'success');
    loadPrepared();
}

async function nurse_prepare_cancel(id) {
    await AjaxLoader.api('nurse/prepare', { method: 'POST', data: { action: 'cancel', id } });
    Dom.toast('已取消预摆', 'success');
    loadPrepared();
}

function nurse_prepare_refresh() { loadPrepared(); }
</script>
