<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">系统设置</h3>
    <p class="page-desc">医院信息与全局参数（分组保存，修改后即时生效）</p>
</div>

<div id="syscfg-groups"></div>

<div class="card">
    <div class="card-header">时间与时区</div>
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">全站时区（显示口径）</label>
            <select class="form-control" id="syscfg-tz"></select>
            <div class="hint">存储与对外接口均为标准 UTC；此处仅决定全站显示时间</div>
        </div>
    </div>
    <button class="btn btn-primary" onclick="admin_sys_configs_saveTz()">保存</button>
</div>

<script>
var _syscfgList = [];
var _SYS_GROUP_LABELS = { system: '系统信息', interface: '接口参数', clinic: '临床参数' };
var _SYS_TZ_COMMON = ['Asia/Shanghai', 'Asia/Urumqi', 'Asia/Hong_Kong', 'Asia/Taipei',
    'Asia/Tokyo', 'Asia/Singapore', 'America/New_York', 'Europe/London', 'UTC'];

async function admin_sys_configs_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    loadConfigs();
    loadTimezone();
}

async function loadTimezone() {
    const r = await AjaxLoader.api('admin/timezone-get', { silent: true });
    const sel = document.getElementById('syscfg-tz');
    if (!sel || !r.data) return;
    let html = '<optgroup label="常用">' + _SYS_TZ_COMMON.map(t => `<option value="${t}">${t}</option>`).join('') + '</optgroup>';
    html += '<optgroup label="全部时区">' + (r.data.timezones || []).map(t => `<option value="${t}">${t}</option>`).join('') + '</optgroup>';
    sel.innerHTML = html;
    sel.value = r.data.timezone || 'Asia/Shanghai';
}

async function admin_sys_configs_saveTz() {
    const tz = document.getElementById('syscfg-tz').value;
    await AjaxLoader.api('admin/timezone-save', { method: 'POST', data: { timezone: tz } });
    Dom.toast('时区已保存，全站显示已按新时区换算', 'success');
}

async function loadConfigs() {
    const r = await AjaxLoader.api('admin/sys-config-list', { silent: true });
    _syscfgList = (r.data || []).filter(c => c.config_key !== 'setup_completed');
    renderSysGroups();
}

function renderSysGroups() {
    const box = document.getElementById('syscfg-groups');
    const groups = {};
    _syscfgList.forEach(c => {
        const g = c.config_group || 'system';
        (groups[g] = groups[g] || []).push(c);
    });
    const keys = Object.keys(groups);
    if (!keys.length) {
        box.innerHTML = '<div class="card"><div class="empty-state">暂无系统配置项</div></div>';
        return;
    }
    box.innerHTML = keys.map(g => `
        <div class="card">
            <div class="card-header">${Format.escape(_SYS_GROUP_LABELS[g] || g)}</div>
            <div class="form-grid">
                ${groups[g].map(c => `
                    <div class="form-group">
                        <label class="form-label">${Format.escape(c.description || c.config_key)}</label>
                        <input class="form-control" data-key="${Format.escape(c.config_key)}" value="${Format.escape(c.config_value || '')}">
                        <div class="hint">${Format.escape(c.config_key)}</div>
                    </div>`).join('')}
            </div>
            <button class="btn btn-primary" onclick="admin_sys_configs_saveGroup('${Format.escape(g)}')">保存</button>
        </div>`).join('');
}

async function admin_sys_configs_saveGroup(group) {
    let saved = 0;
    for (const c of _syscfgList) {
        if ((c.config_group || 'system') !== group) continue;
        const el = document.querySelector(`#syscfg-groups input[data-key="${c.config_key}"]`);
        if (!el || el.value === (c.config_value || '')) continue;
        await AjaxLoader.api('admin/sys-config-save', { method: 'POST', data: {
            config_key: c.config_key,
            config_value: el.value,
            config_group: c.config_group || 'system',
            description: c.description || ''
        }});
        saved++;
    }
    if (saved) Dom.toast('已保存 ' + saved + ' 项', 'success');
    else Dom.toast('没有需要保存的修改', 'info');
    loadConfigs();
}
</script>
