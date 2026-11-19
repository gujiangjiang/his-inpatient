<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="pane-wrap">
    <div class="pane-nav" id="dbcenter-nav">
        <div class="pn-item active" data-dbtab="status" onclick="admin_dbcenter_tab('status')">状态详情</div>
        <div class="pn-item" data-dbtab="browse" onclick="admin_dbcenter_tab('browse')">数据表浏览</div>
        <div class="pn-item" data-dbtab="switch" onclick="admin_dbcenter_tab('switch')">驱动切换</div>
    </div>
    <div class="pane-main">
        <div class="db-pane" data-dbtab="status">
            <div class="card">
                <div class="card-header">数据库状态</div>
                <div class="status-list" id="dbcenter-status"></div>
                <button class="btn btn-small mt-10" onclick="admin_dbcenter_load()">刷新</button>
            </div>
        </div>
        <div class="db-pane" data-dbtab="browse" style="display:none">
            <div class="card">
                <div class="card-header">数据表</div>
                <div class="flex-between mb-10">
                    <select class="form-control" id="dbcenter-table" style="max-width:300px" onchange="admin_dbcenter_table_data()"></select>
                    <span class="text-muted" id="dbcenter-table-count"></span>
                </div>
                <div style="max-height:50vh;overflow:auto"><table class="table" id="dbcenter-table-body"></table></div>
            </div>
        </div>
        <div class="db-pane" data-dbtab="switch" style="display:none">
            <div class="card">
                <div class="card-header">驱动切换（写 config.db，下次请求生效；切换前请确保新库已初始化）</div>
                <div class="form-group">
                    <label class="form-label">驱动</label>
                    <select class="form-control" id="dbcenter-driver" style="max-width:220px" onchange="admin_dbcenter_render_params()">
                        <option value="sqlite">SQLite（文件库）</option>
                        <option value="mysql">MySQL / MariaDB</option>
                        <option value="postgresql">PostgreSQL</option>
                    </select>
                </div>
                <div id="dbcenter-params"></div>
                <div class="flex gap-8 mt-10">
                    <button class="btn" onclick="admin_dbcenter_test()">测试连接</button>
                    <button class="btn btn-primary" onclick="admin_dbcenter_switch()">切换驱动</button>
                </div>
                <p class="hint" id="dbcenter-switch-msg"></p>
            </div>
        </div>
    </div>
</div>
<script>
var _dbcenterParams = {
    sqlite: [{ k: 'path', l: '数据文件路径', d: '' }],
    mysql: [{ k: 'host', l: '主机', d: '127.0.0.1' }, { k: 'dbname', l: '数据库名', d: 'his' }, { k: 'user', l: '用户名', d: 'root' }, { k: 'pass', l: '密码', d: '' }],
    postgresql: [{ k: 'host', l: '主机', d: '127.0.0.1' }, { k: 'dbname', l: '数据库名', d: 'his' }, { k: 'user', l: '用户名', d: 'postgres' }, { k: 'pass', l: '密码', d: '' }]
};

async function admin_dbcenter_init() {
    document.getElementById('main-app').style.display = 'flex';
    const sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) {
        const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
        sidebarEl.innerHTML = sidebar;
    }
    admin_dbcenter_load();
    admin_dbcenter_render_params();
}

function admin_dbcenter_tab(t) {
    document.querySelectorAll('#dbcenter-nav .pn-item').forEach(function (el) {
        el.classList.toggle('active', el.dataset.dbtab === t);
    });
    document.querySelectorAll('#dbcenter-nav ~ .pane-main .db-pane').forEach(function (el) {
        el.style.display = el.dataset.dbtab === t ? '' : 'none';
    });
    if (t === 'browse') admin_dbcenter_tables();
    if (t === 'status') admin_dbcenter_load();
}

async function admin_dbcenter_load() {
    const r = await AjaxLoader.api('admin/db-center?action=status', { silent: true });
    const d = r.data || {};
    const box = document.getElementById('dbcenter-status');
    box.innerHTML = [
        ['驱动', '<span class="badge badge-info">' + Format.escape(d.driver || '') + '</span>'],
        ['数据文件', d.path || '—'],
        ['主机', d.host || '—'],
        ['数据库名', d.dbname || '—'],
        ['表数量', d.table_count != null ? d.table_count : '—']
    ].map(function (row) {
        return '<div class="status-row"><span class="status-label">' + row[0] + '</span><span class="status-value">' + row[1] + '</span></div>';
    }).join('');
}

async function admin_dbcenter_tables() {
    const r = await AjaxLoader.api('admin/db-center?action=status', { silent: true });
    const d = r.data || {};
    const sel = document.getElementById('dbcenter-table');
    sel.innerHTML = '';
    (d.tables || []).forEach(function (t) {
        const opt = document.createElement('option');
        opt.value = t; opt.textContent = t;
        sel.appendChild(opt);
    });
    document.getElementById('dbcenter-table-count').textContent = '共 ' + (d.table_count || 0) + ' 张表';
    admin_dbcenter_table_data();
}

async function admin_dbcenter_table_data() {
    const table = document.getElementById('dbcenter-table').value;
    if (!table) return;
    const r = await AjaxLoader.api('admin/db-center?action=table-data&table=' + encodeURIComponent(table) + '&limit=30', { silent: true });
    const d = r.data || {};
    const cols = d.cols || [];
    const box = document.getElementById('dbcenter-table-body');
    box.innerHTML = '<thead><tr>' + cols.map(function (c) { return '<th>' + Format.escape(c) + '</th>'; }).join('') + '</tr></thead>' +
        '<tbody>' + (d.rows || []).map(function (row) {
            return '<tr>' + cols.map(function (c) {
                const v = row[c];
                return '<td>' + Format.escape(v == null ? '' : String(v)) + '</td>';
            }).join('') + '</tr>';
        }).join('') + '</tbody>';
}

function admin_dbcenter_render_params() {
    const drv = document.getElementById('dbcenter-driver').value;
    const box = document.getElementById('dbcenter-params');
    box.innerHTML = (_dbcenterParams[drv] || []).map(function (p) {
        return '<div class="form-group"><label class="form-label">' + p.l + '</label>' +
            '<input class="form-control" id="dbcp-' + p.k + '" value="' + Format.escape(p.d) + '" ' + (p.k === 'pass' ? 'type="password"' : '') + '></div>';
    }).join('');
    document.getElementById('dbcenter-switch-msg').textContent = '';
}

function _dbcenter_values() {
    const drv = document.getElementById('dbcenter-driver').value;
    const v = { driver: drv };
    (_dbcenterParams[drv] || []).forEach(function (p) { v[p.k] = document.getElementById('dbcp-' + p.k).value; });
    return v;
}

async function admin_dbcenter_test() {
    const r = await AjaxLoader.api('admin/db-center', { method: 'POST', data: Object.assign({ action: 'test' }, _dbcenter_values()) });
    document.getElementById('dbcenter-switch-msg').innerHTML = r.data && r.data.ok
        ? '<span class="text-success">' + Format.escape(r.message || '连接成功') + '</span>'
        : '<span class="text-danger">' + Format.escape(r.message || '连接失败') + '</span>';
}

async function admin_dbcenter_switch() {
    const r = await AjaxLoader.api('admin/db-center', { method: 'POST', data: Object.assign({ action: 'switch' }, _dbcenter_values()) });
    if (r.status === 'success') {
        Dom.toast(r.message || '已切换', 'success');
        document.getElementById('dbcenter-switch-msg').innerHTML = '<span class="text-success">' + Format.escape(r.message || '已切换') + '</span>';
        admin_dbcenter_load();
    } else {
        Dom.toast(r.message || '切换失败', 'error');
    }
}
</script>
