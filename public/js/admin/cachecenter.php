<?php header("Content-Type: text/html; charset=utf-8"); ?>
<link rel="stylesheet" href="/css/admin/admin.css">
<div class="page-head">
    <h3 class="page-title">缓存与性能</h3>
    <p class="page-desc">缓存驱动状态与切换 · 模块化缓存刷新</p>
</div>

<div class="pane-wrap">
    <div class="pane-nav" id="cachecenter-nav">
        <div class="pn-item active" data-cachetab="status" onclick="admin_cachecenter_tab('status')">状态详情</div>
        <div class="pn-item" data-cachetab="driver" onclick="admin_cachecenter_tab('driver')">驱动切换</div>
        <div class="pn-item" data-cachetab="flush" onclick="admin_cachecenter_tab('flush')">缓存刷新</div>
    </div>
    <div class="pane-main">
<div class="db-pane" data-cachetab="status">
    <div class="card">
        <div class="card-header">缓存状态</div>
        <div class="status-list" id="cachecenter-status"></div>
    </div>
</div>

<div class="db-pane" data-cachetab="driver" style="display:none">
    <div class="card">
        <div class="card-header">缓存驱动切换（未安装扩展的驱动会标注；保存前连接测试）</div>
        <div class="form-group">
            <label class="form-label">缓存驱动</label>
            <select class="form-control" id="cachecenter-driver" style="max-width:260px" onchange="admin_cachecenter_params()"></select>
        </div>
        <div id="cachecenter-params"></div>
        <button class="btn btn-primary" onclick="admin_cachecenter_save()">保存驱动</button>
        <p class="hint" id="cachecenter-msg"></p>
    </div>
</div>

<div class="db-pane" data-cachetab="flush" style="display:none">
    <div class="card">
        <div class="card-header">模块化缓存刷新</div>
        <div class="flex gap-8">
            <button class="btn btn-small" onclick="admin_cachecenter_flush('config')">配置缓存</button>
            <button class="btn btn-small" onclick="admin_cachecenter_flush('dict')">字典缓存</button>
            <button class="btn btn-small" onclick="admin_cachecenter_flush('call')">叫号/临时缓存</button>
            <button class="btn btn-small btn-danger" onclick="admin_cachecenter_flush('all')">全部刷新</button>
        </div>
        <p class="hint" id="cachecenter-flush-msg"></p>
    </div>
</div>
    </div>
</div>

<script>
var _cacheDrivers = {};

async function admin_cachecenter_init() {
    var appEl = document.getElementById('main-app'); if (appEl) appEl.style.display = 'flex';
    const sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) {
        const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
        sidebarEl.innerHTML = sidebar;
    }
    admin_cachecenter_tab('status');
}

function admin_cachecenter_tab(t) {
    document.querySelectorAll('.pn-item[data-cachetab]').forEach(function (b) {
        b.classList.toggle('active', b.dataset.cachetab === t);
    });
    document.querySelectorAll('.db-pane[data-cachetab]').forEach(function (el) {
        el.style.display = el.dataset.cachetab === t ? '' : 'none';
    });
    if (t === 'status') admin_cachecenter_load();
    if (t === 'driver') admin_cachecenter_load();
}

async function admin_cachecenter_load() {
    const r = await AjaxLoader.api('admin/cache-center?action=status', { silent: true });
    const d = r.data || {};
    _cacheDrivers = (d.drivers && d.drivers.cache) || {};
    // 状态
    const box = document.getElementById('cachecenter-status');
    const statusBadge = d.status === 'ok'
        ? '<span class="badge badge-success">' + Format.escape(d.status_text || '正常') + '</span>'
        : '<span class="badge badge-danger">' + Format.escape(d.status_text || '降级') + '</span>';
    box.innerHTML = [
        ['缓存状态', statusBadge],
        ['缓存驱动', '<span class="badge badge-info">' + Format.escape(d.driver_label || 'FILE') + '</span>'],
        ['键数量', d.keys != null ? d.keys : '—'],
        ['占用内存', d.memory || '—']
    ].map(function (row) {
        return '<div class="status-row"><span class="status-label">' + row[0] + '</span><span class="status-value">' + row[1] + '</span></div>';
    }).join('') + ((d.notes || []).length ? d.notes.map(function (n) {
        return '<div class="fs-12 text-muted mt-8">' + Format.escape(n) + '</div>';
    }).join('') : '');
    // 驱动下拉
    const sel = document.getElementById('cachecenter-driver');
    if (sel && Object.keys(_cacheDrivers).length) {
        sel.innerHTML = Object.keys(_cacheDrivers).map(function (k) {
            const opt = _cacheDrivers[k];
            const needExt = opt.extension || '';
            const hasExt = needExt === '' || (window.__extLoaded || {}).hasOwnProperty(needExt);
            const mark = (needExt !== '' && !(window.__extLoaded || {})[needExt]) ? '（未安装扩展）' : '';
            return '<option value="' + k + '" ' + (k === d.driver ? 'selected' : '') + '>' + Format.escape(opt.label || k) + mark + '</option>';
        }).join('');
        admin_cachecenter_params();
    }
}

function admin_cachecenter_params() {
    const k = document.getElementById('cachecenter-driver').value;
    const opt = _cacheDrivers[k] || {};
    const box = document.getElementById('cachecenter-params');
    if (!(opt.params || []).length) { box.innerHTML = '<p class="fs-13 text-muted">该驱动无需额外参数（' + Format.escape(opt.label || k) + '）</p>'; return; }
    box.innerHTML = Object.keys(opt.params).map(function (pk) {
        const p = opt.params[pk];
        return '<div class="form-group"><label class="form-label">' + Format.escape(p.label) + '</label>' +
            '<input class="form-control" id="ccp-' + pk + '" value="' + Format.escape(p.default || '') + '" ' + (pk === 'auth' ? 'type="password"' : '') + '></div>';
    }).join('');
}

async function admin_cachecenter_save() {
    const k = document.getElementById('cachecenter-driver').value;
    const opt = _cacheDrivers[k] || {};
    const data = { action: 'driver-save', driver: k };
    Object.keys(opt.params || {}).forEach(function (pk) {
        data[pk] = document.getElementById('ccp-' + pk) ? document.getElementById('ccp-' + pk).value : '';
    });
    const msg = document.getElementById('cachecenter-msg');
    msg.textContent = '保存中…';
    const r = await AjaxLoader.api('admin/cache-center', { method: 'POST', data: data });
    if (r.status === 'success') {
        msg.innerHTML = '<span class="text-success">' + Format.escape(r.message || '已保存') + '</span>';
        admin_cachecenter_load();
    } else {
        msg.innerHTML = '<span class="text-danger">' + Format.escape(r.message || '保存失败') + '</span>';
    }
}

async function admin_cachecenter_flush(scope) {
    const msg = document.getElementById('cachecenter-flush-msg');
    msg.textContent = '刷新中…';
    const r = await AjaxLoader.api('admin/cache-center', { method: 'POST', data: { action: 'flush', scope: scope } });
    if (r.status === 'success') {
        msg.innerHTML = '<span class="text-success">' + Format.escape(r.message || '已刷新') + '</span>';
        admin_cachecenter_load();
    } else {
        msg.innerHTML = '<span class="text-danger">' + Format.escape(r.message || '刷新失败') + '</span>';
    }
}
</script>
