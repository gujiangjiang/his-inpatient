<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">床位管理</h3>
        <p class="page-desc">按病区查看床位占用情况</p>
    </div>
    <button class="btn" onclick="patients_beds_load()">刷新</button>
</div>

<div class="card">
    <div class="ward-tabs" id="ward-tabs"></div>
    <div class="ward-stat" id="ward-stat"></div>
    <div class="bed-grid" id="bed-grid"></div>
</div>

<script>
var _bedsWards = [];
var _bedsCurrent = null;

async function patients_beds_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const user = Auth.user;
    const sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    const r = await AjaxLoader.api('patients/beds?summary=1', { silent: true });
    _bedsWards = r.data || [];
    if (_bedsWards.length === 0) {
        document.getElementById('bed-grid').innerHTML = '<div class="empty-state">暂无病区</div>';
        return;
    }
    _bedsCurrent = _bedsWards[0].ward_id;
    patients_beds_renderTabs();
    patients_beds_load();
}

function patients_beds_renderTabs() {
    document.getElementById('ward-tabs').innerHTML = _bedsWards.map(w => {
        const occupied = Number(w.occupied || 0);
        const total = Number(w.total || 0);
        const full = total > 0 && occupied >= total;
        return `<button type="button" class="ward-tab${w.ward_id == _bedsCurrent ? ' active' : ''}" onclick="patients_beds_select(${w.ward_id})">
            ${Format.escape(w.ward_name)}
            <span class="ward-tab-count${full ? ' full' : ''}">${occupied}/${total}</span>
        </button>`;
    }).join('');
}

function patients_beds_select(wardId) {
    _bedsCurrent = wardId;
    patients_beds_renderTabs();
    patients_beds_load();
}

function patients_beds_load() {
    if (!_bedsCurrent) return;
    AjaxLoader.api('patients/beds?ward_id=' + _bedsCurrent, { silent: true }).then(r => {
        const beds = r.data || [];
        const occupied = beds.filter(b => b.status === 'occupied').length;
        document.getElementById('ward-stat').innerHTML =
            `共 <b>${beds.length}</b> 床 · 占用 <b class="text-danger">${occupied}</b> · 空闲 <b class="text-success">${beds.length - occupied}</b>`;
        const grid = document.getElementById('bed-grid');
        if (!beds.length) {
            grid.innerHTML = '<div class="empty-state">该病区暂无床位</div>';
            return;
        }
        grid.innerHTML = beds.map(b => {
            const state = b.status === 'occupied' ? ' occupied' : ' free';
            const level = b.status === 'occupied' ? ' level-' + (b.nursing_level || '3') : '';
            const critical = b.is_critical == 1 ? ' critical' : '';
            const label = b.status === 'occupied'
                ? Format.escape(b.patient_name || '') + (b.is_critical == 1 ? ' <span class="bed-critical-dot"></span>' : '')
                : '空闲';
            return `<div class="bed-cell${state}${level}${critical}">
                <div class="bed-no">${Format.escape(b.bed_no)}</div>
                <div class="bed-patient">${label}</div>
            </div>`;
        }).join('');
    }).catch(() => {});
}
</script>
