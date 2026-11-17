<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">系统设置</h3>
    <p class="page-desc">医院信息与全局参数（分组保存，修改后即时生效）</p>
</div>

<div class="card syscfg-brand-card">
    <div class="card-header">医院品牌</div>
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">医院名称 <span class="text-danger">*</span></label>
            <input class="form-control" id="syscfg-hospital-name" maxlength="100" placeholder="请输入医院名称" required>
            <div class="hint">显示于左上角、浏览器标题和 PWA 名称</div>
        </div>
        <div class="form-group">
            <label class="form-label">医院第二名称（选填）</label>
            <input class="form-control" id="syscfg-hospital-name2" maxlength="100" placeholder="如英文名、院区名或简称">
            <div class="hint">显示于主名称下方，可留空</div>
        </div>
    </div>
    <div class="syscfg-brand-logo">
        <div class="syscfg-logo-box"><img id="syscfg-logo-preview" alt="医院 Logo 预览"></div>
        <div class="syscfg-logo-main">
            <div class="syscfg-logo-state" id="syscfg-logo-state">加载中...</div>
            <div class="hint">支持 PNG、JPG、WebP、SVG，最大 4MB；浏览器会安全转换为 PNG 并生成 PWA 图标</div>
            <input type="file" id="syscfg-logo-file" accept="image/png,image/jpeg,image/webp,image/svg+xml" hidden>
            <div class="syscfg-logo-actions">
                <button type="button" class="btn" onclick="document.getElementById('syscfg-logo-file').click()">选择 Logo</button>
                <button type="button" class="btn btn-primary" onclick="admin_sys_configs_uploadLogo()">上传 / 更新</button>
                <button type="button" class="btn btn-danger" id="syscfg-logo-remove" onclick="admin_sys_configs_removeLogo()">恢复默认</button>
            </div>
        </div>
    </div>
    <button class="btn btn-primary" onclick="admin_sys_configs_saveBrandNames()">保存医院名称</button>
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
var _syscfgBrand = null;
var _SYS_GROUP_LABELS = { system: '系统信息', interface: '接口参数', clinic: '临床参数' };
var _SYS_TZ_COMMON = ['Asia/Shanghai', 'Asia/Urumqi', 'Asia/Hong_Kong', 'Asia/Taipei',
    'Asia/Tokyo', 'Asia/Singapore', 'America/New_York', 'Europe/London', 'UTC'];

async function admin_sys_configs_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    await Promise.all([loadConfigs(), loadTimezone(), loadBranding()]);
    const file = document.getElementById('syscfg-logo-file');
    if (file) file.addEventListener('change', admin_sys_configs_previewLogo);
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
    const name = _syscfgList.find(c => c.config_key === 'hospital_name');
    const name2 = _syscfgList.find(c => c.config_key === 'hospital_name2');
    document.getElementById('syscfg-hospital-name').value = name ? (name.config_value || '') : '';
    document.getElementById('syscfg-hospital-name2').value = name2 ? (name2.config_value || '') : '';
    renderSysGroups();
}

function renderSysGroups() {
    const box = document.getElementById('syscfg-groups');
    const groups = {};
    _syscfgList.filter(c => !['hospital_name', 'hospital_name2'].includes(c.config_key)).forEach(c => {
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

async function loadBranding() {
    const r = await AjaxLoader.api('auth/setup-status', { silent: true });
    _syscfgBrand = r.data || {};
    const img = document.getElementById('syscfg-logo-preview');
    if (img && _syscfgBrand.logo_url) img.src = _syscfgBrand.logo_url;
    const state = document.getElementById('syscfg-logo-state');
    if (state) state.textContent = _syscfgBrand.has_custom_logo ? '当前使用自定义医院 Logo' : '当前使用系统默认 SVG Logo';
    const remove = document.getElementById('syscfg-logo-remove');
    if (remove) remove.disabled = !_syscfgBrand.has_custom_logo;
}

async function admin_sys_configs_saveBrandNames() {
    const name = document.getElementById('syscfg-hospital-name').value.trim();
    const name2 = document.getElementById('syscfg-hospital-name2').value.trim();
    if (!name) { Dom.toast('医院名称为必填项', 'error'); return; }
    const current = Object.fromEntries(_syscfgList.map(c => [c.config_key, c.config_value || '']));
    if (name !== (current.hospital_name || '')) {
        await AjaxLoader.api('admin/sys-config-save', { method: 'POST', data: { config_key: 'hospital_name', config_value: name } });
    }
    if (name2 !== (current.hospital_name2 || '')) {
        await AjaxLoader.api('admin/sys-config-save', { method: 'POST', data: { config_key: 'hospital_name2', config_value: name2 } });
    }
    Dom.toast('医院名称已保存', 'success');
    await Promise.all([loadConfigs(), loadBranding(), setupHospitalName()]);
}

function admin_sys_configs_previewLogo() {
    const file = document.getElementById('syscfg-logo-file').files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    const img = document.getElementById('syscfg-logo-preview');
    img.onload = () => URL.revokeObjectURL(url);
    img.src = url;
    document.getElementById('syscfg-logo-state').textContent = '已选择：' + file.name + '（尚未上传）';
}

function adminBrandImage(file) {
    return new Promise((resolve, reject) => {
        if (!file || file.size > 4 * 1024 * 1024) { reject(new Error('请选择不超过 4MB 的图片')); return; }
        if (!['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'].includes(file.type)) { reject(new Error('仅支持 PNG、JPG、WebP 或 SVG')); return; }
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('无法解析图片')); };
        img.src = url;
    });
}

function adminBrandPng(img, width, height, padding, background) {
    return new Promise((resolve, reject) => {
        const canvas = document.createElement('canvas');
        canvas.width = width; canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (background) { ctx.fillStyle = background; ctx.fillRect(0, 0, width, height); }
        const scale = Math.min((width - padding * 2) / img.naturalWidth, (height - padding * 2) / img.naturalHeight);
        const w = Math.max(1, Math.round(img.naturalWidth * scale));
        const h = Math.max(1, Math.round(img.naturalHeight * scale));
        ctx.drawImage(img, Math.round((width - w) / 2), Math.round((height - h) / 2), w, h);
        canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('图片转换失败')), 'image/png', 0.95);
    });
}

async function admin_sys_configs_uploadLogo() {
    try {
        const file = document.getElementById('syscfg-logo-file').files[0];
        const img = await adminBrandImage(file);
        const scale = Math.min(1, 1024 / img.naturalWidth, 512 / img.naturalHeight);
        const logo = await adminBrandPng(img, Math.max(16, Math.round(img.naturalWidth * scale)), Math.max(16, Math.round(img.naturalHeight * scale)), 0, '');
        const icon192 = await adminBrandPng(img, 192, 192, 20, '#f3f5f8');
        const icon512 = await adminBrandPng(img, 512, 512, 56, '#f3f5f8');
        const form = new FormData();
        form.append('action', 'upload');
        form.append('logo', logo, 'hospital-logo.png');
        form.append('icon192', icon192, 'hospital-icon-192.png');
        form.append('icon512', icon512, 'hospital-icon-512.png');
        const r = await AjaxLoader.api('admin/branding-save', { method: 'POST', data: form });
        if (r.data) applyHospitalBranding(r.data);
        document.getElementById('syscfg-logo-file').value = '';
        await loadBranding();
    } catch (e) { Dom.toast(e.message, 'error'); }
}

function admin_sys_configs_removeLogo() {
    Dom.modal('恢复默认 Logo', '<p>确认删除当前医院 Logo，并恢复系统默认 SVG 图标？</p>', [
        { text: '恢复默认', type: 'danger', onclick: async () => {
            const form = new FormData(); form.append('action', 'remove');
            const r = await AjaxLoader.api('admin/branding-save', { method: 'POST', data: form });
            if (r.data) applyHospitalBranding(r.data);
            await loadBranding();
        } },
        { text: '取消', type: 'default' }
    ]);
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
