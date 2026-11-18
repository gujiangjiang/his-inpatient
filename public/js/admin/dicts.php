<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">字典管理</h3>
        <p class="page-desc">全院统一字典（存于主数据库）：病历模板下拉字段以字典键引用，修改后全院模板即时生效；每行一个选项</p>
    </div>
    <button class="btn btn-primary" onclick="admin_dicts_new()">新增字典</button>
</div>
<div class="card list-filter">
    <div class="filter-item" style="flex:1;min-width:220px">
        <input class="form-control" id="dict-search" placeholder="搜索字典名称 / 键" style="width:100%" oninput="admin_dicts_debounced()">
    </div>
    <span class="text-muted" id="dict-count"></span>
</div>
<div id="dict-list"></div>

<script>
var _dictRows = [];

async function admin_dicts_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_admin.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    admin_dicts_load();
}

function admin_dicts_debounced() {
    clearTimeout(_dictTimer);
    _dictTimer = setTimeout(admin_dicts_render, 250);
}

async function admin_dicts_load() {
    const r = await AjaxLoader.api('admin/dict-list', { silent: true });
    _dictRows = r.data || [];
    admin_dicts_render();
}

function admin_dicts_render() {
    const box = document.getElementById('dict-list');
    const kw = (document.getElementById('dict-search').value || '').trim().toLowerCase();
    const list = _dictRows.filter(function (d) {
        if (!kw) return true;
        return (d.name || '').toLowerCase().includes(kw) || (d.dkey || '').toLowerCase().includes(kw);
    });
    document.getElementById('dict-count').textContent = '共 ' + list.length + ' 个字典';
    let html = '';
    list.forEach(function (d, i) {
        html += '<div class="card">'
            + '<div class="card-header">' + Format.escape(d.name)
            + (d.builtin ? ' <span class="badge badge-info">内置</span>' : ' <span class="badge badge-default">自定义</span>')
            + ' <span class="fs-12 text-muted">键：' + Format.escape(d.dkey)
            + (d.updated_at ? ' · ' + Format.escape(String(d.updated_at).slice(0, 16)) : '') + '</span></div>'
            + '<div class="form-grid">'
            + '<div class="form-group"><label class="form-label">名称</label>'
            + '<input class="form-control" id="dict-name-' + i + '" value="' + Format.escape(d.name) + '"></div>'
            + '<div class="form-group"><label class="form-label">选项（每行一个）</label>'
            + '<textarea class="form-control" rows="6" id="dict-items-' + i + '">' + Format.escape(d.items.join('\n')) + '</textarea></div>'
            + '</div>'
            + '<button class="btn btn-primary" onclick="admin_dicts_save(' + i + ')">保存</button>'
            + '</div>';
    });
    if (!list.length) html = '<div class="empty-tip">暂无匹配字典</div>';
    box.innerHTML = html;
}

function admin_dicts_new() {
    const content = `
        <div class="form-grid">
            <div class="form-group"><label class="form-label">字典键（字母/数字/下划线，如 blood_type）</label>
                <input class="form-control" id="dict-new-key" placeholder="blood_type"></div>
            <div class="form-group"><label class="form-label">名称</label>
                <input class="form-control" id="dict-new-name" placeholder="血型"></div>
            <div class="form-group colspan-2"><label class="form-label">选项（每行一个）</label>
                <textarea class="form-control" rows="5" id="dict-new-items" placeholder="A&#10;B&#10;O&#10;AB"></textarea></div>
        </div>`;
    Dom.modal('新增字典', content, [
        { text: '新增', type: 'primary', onclick: async function () {
            const dkey = (document.getElementById('dict-new-key').value || '').trim();
            const name = (document.getElementById('dict-new-name').value || '').trim();
            const items = (document.getElementById('dict-new-items').value || '').split('\n').map(x => x.trim()).filter(Boolean);
            if (!dkey || !name) { Dom.toast('请填写字典键与名称', 'error'); return; }
            if (!items.length) { Dom.toast('选项不能为空', 'error'); return; }
            const r = await AjaxLoader.api('admin/dict-save', { method: 'POST', data: { dkey: dkey, name: name, items: items } });
            if (r.status === 'success') { Dom.toast(r.message || '字典已保存（全院即时生效）', 'success'); admin_dicts_load(); }
            else Dom.toast(r.message || '保存失败', 'error');
        } },
        { text: '取消', type: 'default' }
    ]);
}

async function admin_dicts_save(i) {
    const d = _dictRows[i];
    const name = (document.getElementById('dict-name-' + i).value || '').trim();
    const items = (document.getElementById('dict-items-' + i).value || '').split('\n').map(x => x.trim()).filter(Boolean);
    if (!name) { Dom.toast('请填写字典名称', 'error'); return; }
    if (!items.length) { Dom.toast('选项不能为空', 'error'); return; }
    const r = await AjaxLoader.api('admin/dict-save', { method: 'POST', data: { dkey: d.dkey, name: name, items: items } });
    if (r.status === 'success') { Dom.toast(r.message || '字典已保存（全院即时生效）', 'success'); admin_dicts_load(); }
    else Dom.toast(r.message || '保存失败', 'error');
}
</script>
