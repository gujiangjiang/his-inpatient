<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">字典管理</h3>
        <p class="page-desc">全院统一字典：左侧选择字典，右侧编辑名称与选项（每行一个，即时生效）</p>
    </div>
    <button class="btn btn-primary" onclick="admin_dicts_new()">新增字典</button>
</div>
<div class="pane-wrap">
    <div class="pane-nav dict-nav">
        <div class="filter-item" style="margin:4px 6px 8px">
            <input class="form-control" id="dict-search" placeholder="搜索名称 / 键" style="width:100%" oninput="admin_dicts_debounced()">
        </div>
        <div id="dict-nav-list"></div>
        <div class="pn-item" style="color:#909399;cursor:default;margin-top:6px" id="dict-count"></div>
    </div>
    <div class="pane-main">
        <div class="card" id="dict-editor">
            <div class="card-header" id="dict-editor-title">选择左侧字典进行编辑</div>
            <div id="dict-editor-body" class="empty-tip">左侧选择字典后，此处显示名称与选项</div>
        </div>
    </div>
</div>

<script>
var _dictRows = [];
var _dictActive = -1;

async function admin_dicts_init() {
    await App.mountShell('admin');
    admin_dicts_load();
}

function admin_dicts_debounced() {
    clearTimeout(_dictTimer);
    _dictTimer = setTimeout(admin_dicts_render_nav, 250);
}

async function admin_dicts_load() {
    const r = await AjaxLoader.api('admin/dict-list', { silent: true });
    _dictRows = r.data || [];
    admin_dicts_render_nav();
}

function admin_dicts_render_nav() {
    const kw = (document.getElementById('dict-search').value || '').trim().toLowerCase();
    const list = _dictRows.filter(function (d) {
        if (!kw) return true;
        return (d.name || '').toLowerCase().includes(kw) || (d.dkey || '').toLowerCase().includes(kw);
    });
    document.getElementById('dict-count').textContent = '共 ' + list.length + ' 个';
    const box = document.getElementById('dict-nav-list');
    box.innerHTML = list.map(function (d, i) {
        const idx = _dictRows.indexOf(d);
        return '<div class="pn-item' + (idx === _dictActive ? ' active' : '') + '" data-idx="' + idx + '" onclick="admin_dicts_select(' + idx + ')">' +
            Format.escape(d.name) + (d.builtin ? ' <span class="badge badge-info">内置</span>' : '') +
            '<div class="fs-12 text-muted" style="font-size:11px">' + Format.escape(d.dkey) + '</div></div>';
    }).join('');
    if (!list.length) box.innerHTML = '<div class="empty-tip">暂无匹配字典</div>';
    if (_dictActive >= 0) admin_dicts_editor();
}

function admin_dicts_select(idx) {
    _dictActive = idx;
    document.querySelectorAll('#dict-nav-list .pn-item').forEach(function (el) {
        el.classList.toggle('active', Number(el.dataset.idx) === idx);
    });
    admin_dicts_editor();
}

function admin_dicts_editor() {
    const d = _dictRows[_dictActive];
    const box = document.getElementById('dict-editor-body');
    const title = document.getElementById('dict-editor-title');
    if (!d) { title.textContent = '选择左侧字典进行编辑'; box.className = 'empty-tip'; box.textContent = '左侧选择字典后，此处显示名称与选项'; return; }
    title.innerHTML = Format.escape(d.name) + (d.builtin ? ' <span class="badge badge-info">内置</span>' : ' <span class="badge badge-default">自定义</span>');
    box.className = '';
    box.innerHTML = '<div class="form-grid">' +
        '<div class="form-group"><label class="form-label">名称</label>' +
        '<input class="form-control" id="dict-name" value="' + Format.escape(d.name) + '"></div>' +
        '<div class="form-group"><label class="form-label">键</label>' +
        '<input class="form-control" value="' + Format.escape(d.dkey) + '" disabled></div>' +
        '<div class="form-group colspan-2"><label class="form-label">选项（每行一个）</label>' +
        '<textarea class="form-control" rows="12" id="dict-items">' + Format.escape(d.items.join('\n')) + '</textarea></div>' +
        '</div>' +
        '<button class="btn btn-primary" onclick="admin_dicts_save()">保存</button>';
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
            if (r.status === 'success') { Dom.toast(r.message || '字典已保存', 'success'); admin_dicts_load(); }
            else Dom.toast(r.message || '保存失败', 'error');
        } },
        { text: '取消', type: 'default' }
    ]);
}

async function admin_dicts_save() {
    const d = _dictRows[_dictActive];
    if (!d) return;
    const name = (document.getElementById('dict-name').value || '').trim();
    const items = (document.getElementById('dict-items').value || '').split('\n').map(x => x.trim()).filter(Boolean);
    if (!name) { Dom.toast('请填写字典名称', 'error'); return; }
    if (!items.length) { Dom.toast('选项不能为空', 'error'); return; }
    const r = await AjaxLoader.api('admin/dict-save', { method: 'POST', data: { dkey: d.dkey, name: name, items: items } });
    if (r.status === 'success') { Dom.toast(r.message || '字典已保存（全院即时生效）', 'success'); admin_dicts_load(); }
    else Dom.toast(r.message || '保存失败', 'error');
}
</script>
