<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">护士站总览</h3>
        <p class="page-desc">在院患者待办（待核查 / 未发药）</p>
    </div>
    <button class="btn" onclick="nurse_station_load()">刷新</button>
</div>

<div class="card">
    <div class="card-header">在院患者</div>
    <div id="nurse-patients"></div>
</div>

<script>
async function nurse_station_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_nurse.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    nurse_station_load();
}

async function nurse_station_load() {
    const result = await AjaxLoader.api('nurse/patient-list', { silent: true });
    const container = document.getElementById('nurse-patients');
    container.innerHTML = '';
    (result.data || []).forEach(p => {
        const badges = [];
        if (p.pending_cnt > 0) badges.push('<span class="badge badge-pending">待核查医嘱 ' + p.pending_cnt + '</span>');
        if (p.undispensed_cnt > 0) badges.push('<span class="badge badge-warning">未发药 ' + p.undispensed_cnt + '</span>');
        if (!badges.length) badges.push('<span class="badge badge-default">无待办</span>');
        const div = document.createElement('div');
        div.className = 'patient-card';
        div.innerHTML = `
            <div class="patient-name">${Format.escape(p.name)}</div>
            <div class="patient-info">床号: ${Format.escape(p.bed_no || '-')} | 病区: ${Format.escape(p.ward_name || '-')} | 诊断: ${Format.escape(p.admission_diagnosis || '-')}</div>
            <div class="patient-info">${badges.join(' ')}</div>
        `;
        div.onclick = () => nurse_station_detail(p.id);
        container.appendChild(div);
    });
}

var _nsdPatientId = null;

function nurse_station_detail(patientId) {
    _nsdPatientId = patientId;
    Dom.modal('患者详情', '<div id="nsd-box"><div class="empty-state">加载中…</div></div>');
    nurse_station_detail_reload();
}

/* 就地刷新详情弹窗 (核查/执行后无需刷新页面) */
async function nurse_station_detail_reload() {
    if (!_nsdPatientId) return;
    const r = await AjaxLoader.api('nurse/order-list?patient_id=' + _nsdPatientId, { silent: true });
    const box = document.getElementById('nsd-box');
    if (!box) return;
    const rows = r.data || [];
    const catLabel = c => c === 'medication' ? '药品' : '医嘱';
    const actHtml = o => {
        if (o.status === 'invalid') return '<span class="fs-12 text-muted">已毁方作废</span>';
        if (o.status === 'pending') {
            return `<button class="btn btn-small btn-success" onclick="nurse_station_verify(${o.id})">核查</button>`;
        }
        if (o.category === 'medication' && !o.dispensed_id) {
            return '<span class="fs-12 text-muted">待发药后执行</span>';
        }
        return `<button class="btn btn-small btn-success" onclick="nurse_station_execute(${o.id})">执行</button>`;
    };
    const body = rows.length
        ? rows.map(o => `
            <tr class="${o.status === 'invalid' ? 'os-txt-invalid' : ''}">
                <td>${Format.escape(o.content)}</td>
                <td>${catLabel(o.category)}</td>
                <td>${Format.escape([o.usage_name, o.freq_code].filter(Boolean).join(' · '))}</td>
                <td><span class="badge ${Format.orderStatusClass(o.status)}">${Format.orderStatus(o.status)}</span></td>
                <td>${actHtml(o)}</td>
            </tr>`).join('')
        : '<tr><td colspan="5"><div class="empty-state">暂无待办医嘱</div></td></tr>';
    box.innerHTML = `
        <table class="table">
            <thead><tr><th>医嘱</th><th>类型</th><th>途径 · 频次</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>${body}</tbody>
        </table>`;
}

async function nurse_station_verify(orderId) {
    await AjaxLoader.api('orders/verify', { method: 'POST', data: { id: orderId } });
    Dom.toast('已核查', 'success');
    await nurse_station_detail_reload();
    nurse_station_load();
}

async function nurse_station_execute(orderId) {
    const result = await AjaxLoader.api('nurse/nursing-action', { method: 'POST', data: { order_id: orderId, action: 'execute' } });
    if (result.status === 'success') {
        Dom.toast('执行成功', 'success');
        await nurse_station_detail_reload();
        nurse_station_load();
    }
}
</script>
