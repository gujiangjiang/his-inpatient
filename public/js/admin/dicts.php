<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head">
    <h3 class="page-title">字典管理</h3>
    <p class="page-desc">全院统一字典（存于主数据库）：病历模板下拉字段以字典键引用，修改后全院模板即时生效；每行一个选项</p>
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

async function admin_dicts_load() {
    const r = await AjaxLoader.api('admin/dict-list', { silent: true });
    _dictRows = r.data || [];
    admin_dicts_render();
}

function admin_dicts_render() {
    const box = document.getElementById('dict-list');
    let html = '';
    _dictRows.forEach(function (d, i) {
        html += '<div class="card">'
            + '<div class="card-header">' + Format.escape(d.name)
            + ' <span class="fs-12 text-muted">键：' + Format.escape(d.dkey) + (d.builtin ? ' · 内置' : '')
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
    html += '<div class="card">'
        + '<div class="card-header">新增字典</div>'
        + '<div class="form-grid">'
        + '<div class="form-group"><label class="form-label">字典键（字母/数字/下划线，如 blood_type）</label>'
        + '<input class="form-control" id="dict-new-key" placeholder="blood_type"></div>'
        + '<div class="form-group"><label class="form-label">名称</label>'
        + '<input class="form-control" id="dict-new-name" placeholder="血型"></div>'
        + '<div class="form-group"><label class="form-label">选项（每行一个）</label>'
        + '<textarea class="form-control" rows="4" id="dict-new-items" placeholder="A\nB\nO\nAB"></textarea></div>'
        + '</div>'
        + '<button class="btn btn-primary" onclick="admin_dicts_save(-1)">新增</button>'
        + '</div>';
    box.innerHTML = html;
}

async function admin_dicts_save(i) {
    let dkey, name, items;
    if (i < 0) {
        dkey = (document.getElementById('dict-new-key').value || '').trim();
        name = (document.getElementById('dict-new-name').value || '').trim();
        items = (document.getElementById('dict-new-items').value || '').split('\n');
    } else {
        const d = _dictRows[i];
        dkey = d.dkey;
        name = (document.getElementById('dict-name-' + i).value || '').trim();
        items = (document.getElementById('dict-items-' + i).value || '').split('\n');
    }
    items = items.map(function (x) { return x.trim(); }).filter(Boolean);
    if (!dkey || !name) { Dom.toast('请填写字典键与名称', 'error'); return; }
    if (!items.length) { Dom.toast('选项不能为空', 'error'); return; }
    await AjaxLoader.api('admin/dict-save', { method: 'POST', data: { dkey: dkey, name: name, items: items } });
    Dom.toast('字典已保存（全院即时生效）', 'success');
    admin_dicts_load();
}
</script>
