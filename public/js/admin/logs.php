<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">日志中心</h3>
    <p class="page-desc">服务器日志 · 操作日志 · 接口日志</p>
</div>

<div class="card" style="padding:10px 16px">
    <div class="sub-tabs" id="logs-tabs">
        <button class="sub-tab active" data-tab="server" onclick="admin_logs_tab('server')">服务器日志</button>
        <button class="sub-tab" data-tab="operation" onclick="admin_logs_tab('operation')">操作日志</button>
        <button class="sub-tab" data-tab="interface" onclick="admin_logs_tab('interface')">接口日志</button>
    </div>
</div>

<div class="pane-wrap">
    <div class="pane-nav" id="logs-cat-nav">
        <div class="pn-item active" data-cat="" onclick="admin_logs_cat(this)">全部 <span class="mini-badge" data-cat-count></span></div>
    </div>
    <div class="pane-main">
        <div class="card list-filter">
            <div class="filter-item" id="logs-dir-wrap" style="display:none">
                <label>方向</label>
                <select class="form-control" id="logs-dir" onchange="admin_logs_refresh()">
                    <option value="">全部</option><option value="inbound">入向</option><option value="outbound">出向</option>
                </select>
            </div>
            <div class="filter-item">
                <label>级别</label>
                <select class="form-control" id="logs-level" onchange="admin_logs_refresh()">
                    <option value="">全部</option><option value="normal">普通</option><option value="info">信息</option>
                    <option value="warning">警告</option><option value="error">错误</option>
                </select>
            </div>
            <div class="filter-item">
                <label>日期</label>
                <input type="text" class="form-control" id="logs-date" data-dtpicker="date" placeholder="选择日期" onchange="admin_logs_refresh()">
            </div>
            <div class="filter-item" style="flex:1;min-width:180px">
                <input class="form-control" id="logs-kw" placeholder="搜索摘要/明细/操作人/目标" style="width:100%" oninput="admin_logs_debounced()">
            </div>
            <div class="filter-item">
                <label class="form-checkbox"><input type="checkbox" id="logs-live" onchange="admin_logs_refresh()"> 实时</label>
            </div>
        </div>
        <div class="card">
            <div class="log-view" id="logs-view" style="max-height:60vh;overflow-y:auto;padding:6px 10px"></div>
        </div>
    </div>
</div>

<script>
var _logTab = 'server';
var _logCat = '';
var _logLastId = 0;
var _logTimer = null;
var _logLiveTimer = null;
var _logHasMore = false;
var _logLoading = false;

async function admin_logs_init() {
    await App.mountShell('admin');
    if (window.DateTimePicker) DateTimePicker.initInputs(document);
    admin_logs_tab('server');
}

function admin_logs_tab(t) {
    _logTab = t;
    _logCat = '';
    document.querySelectorAll('#logs-tabs .sub-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === t));
    document.getElementById('logs-dir-wrap').style.display = t === 'interface' ? '' : 'none';
    if (t === 'interface') { /* 左栏只留「全部」 */ } else { }
    admin_logs_refresh();
}

function admin_logs_cat(el) {
    document.querySelectorAll('#logs-cat-nav .pn-item').forEach(n => n.classList.toggle('active', n === el));
    _logCat = el.dataset.cat || '';
    admin_logs_refresh();
}

function admin_logs_debounced() {
    clearTimeout(_logTimer);
    _logTimer = setTimeout(admin_logs_refresh, 250);
}

function admin_logs_refresh() {
    clearInterval(_logLiveTimer);
    _logLiveTimer = null;
    if (_logTab === 'server') { admin_logs_load_server(); return; }
    admin_logs_load();
    if (document.getElementById('logs-live').checked) {
        _logLiveTimer = setInterval(admin_logs_poll, 3000);
    }
}

function _logsFilter() {
    return {
        channel: _logTab, category: _logCat,
        direction: document.getElementById('logs-dir').value,
        level: document.getElementById('logs-level').value,
        date: document.getElementById('logs-date').value,
        kw: document.getElementById('logs-kw').value.trim()
    };
}

function _logsLevelBadge(lv) {
    const map = { normal: 'badge-default', info: 'badge-info', warning: 'badge-warning', error: 'badge-danger' };
    return '<span class="badge ' + (map[lv] || 'badge-default') + '">' + (lv || 'info') + '</span>';
}

function _logsRowHtml(e) {
    const dirBadge = e.direction === 'inbound' ? '<span class="badge badge-info">入向</span>' :
        (e.direction === 'outbound' ? '<span class="badge badge-warning">出向</span>' : '');
    const meta = [e.username, e.remote_ip, e.target].filter(Boolean).join(' · ');
    return '<div class="log-line">' +
        '<span class="log-time">' + Format.escape(String(e.created_at || '').slice(5, 19)) + '</span>' +
        '<span class="log-level">' + _logsLevelBadge(e.level) + '</span>' + dirBadge +
        '<div class="log-main" style="flex:1;min-width:0">' +
            '<div>' + Format.escape(e.summary || '') + ' <span class="fs-12 text-muted">' + Format.escape(e.action || '') + '</span></div>' +
            (e.detail ? '<div class="fs-12 text-muted">' + Format.escape(e.detail) + '</div>' : '') +
            (meta ? '<div class="fs-12 text-muted">' + Format.escape(meta) + '</div>' : '') +
        '</div></div>';
}

async function admin_logs_load() {
    const r = await AjaxLoader.api('admin/logs?action=list&' + new URLSearchParams(_logsFilter()).toString(), { silent: true });
    const d = r.data || {};
    const view = document.getElementById('logs-view');
    if (d.error) { view.innerHTML = '<div class="empty-tip" style="color:#e5484d">' + Format.escape(d.error) + '</div>'; return; }
    view.innerHTML = (d.list || []).map(_logsRowHtml).join('') || '<div class="empty-tip">暂无日志</div>';
    _logLastId = (d.list && d.list.length) ? d.list[d.list.length - 1].id : _logLastId;
    _logHasMore = !!d.has_more;
    _logsApplyCounts(d.counts || {});
}

async function admin_logs_poll() {
    if (_logTab === 'server') return;
    const r = await AjaxLoader.api('admin/logs?action=latest&after_id=' + _logLastId + '&' + new URLSearchParams(_logsFilter()).toString(), { silent: true });
    const d = r.data || {};
    const rows = d.list || [];
    if (rows.length) {
        const view = document.getElementById('logs-view');
        view.innerHTML = view.innerHTML.replace('<div class="empty-tip">暂无日志</div>', '');
        view.innerHTML += rows.map(_logsRowHtml).join('');
        _logLastId = rows[rows.length - 1].id;
        view.scrollTop = view.scrollHeight;
    }
    if (d.counts) _logsApplyCounts(d.counts);
}

function _logsApplyCounts(counts) {
    const nav = document.getElementById('logs-cat-nav');
    const total = Object.values(counts || {}).reduce((s, v) => s + v, 0);
    const all = nav.querySelector('[data-cat=""] .mini-badge');
    if (all) all.textContent = total || '';
}

async function admin_logs_load_server() {
    const r = await AjaxLoader.api('admin/logs?action=server', { silent: true });
    const d = r.data || {};
    const view = document.getElementById('logs-view');
    if (!d.exists) { view.innerHTML = '<div class="empty-tip">暂无服务器日志文件（data/logs/app.log）</div>'; return; }
    view.innerHTML = (d.lines || []).map(l =>
        '<div class="log-line"><div class="log-main" style="flex:1;min-width:0;font-family:ui-monospace,monospace;font-size:12px">' + Format.escape(l) + '</div></div>'
    ).join('') || '<div class="empty-tip">日志文件为空</div>';
}
</script>
