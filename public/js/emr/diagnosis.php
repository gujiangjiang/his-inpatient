<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">诊断</h3>
        <p class="page-desc">患者诊断分类管理与常用诊断模板</p>
    </div>
    <button class="btn" onclick="emr_diagnosis_load()">刷新</button>
</div>

<div class="card">
    <div class="card-header">添加诊断</div>
    <div class="diag-add">
        <div class="diag-add-input">
            <input type="text" id="diag-input" class="form-control" placeholder="搜索诊断 / ICD-10 编码 / 拼音首字母" autocomplete="off">
            <input type="hidden" id="diag-code">
        </div>
        <label class="fs-13" style="white-space:nowrap"><input type="checkbox" id="diag-save-tpl"> 存为常用诊断</label>
        <button class="btn btn-primary" onclick="emr_diagnosis_add()">添加到当前分类</button>
    </div>
    <div class="apply-panels" id="diag-tpl-box"></div>
</div>

<div class="tabs" id="diag-tabs"></div>
<div class="card">
    <div class="card-header" id="diag-list-title">诊断列表</div>
    <table class="table">
        <thead><tr><th>诊断名称</th><th>ICD-10</th><th>添加人</th><th>时间</th><th>操作</th></tr></thead>
        <tbody id="diag-list-body"></tbody>
    </table>
    <div id="diag-sync-hint" class="hidden" style="margin-top:10px">
        <button class="btn btn-small" disabled title="待电子病历系统对接后开放">出院诊断同步入院诊断（预留）</button>
        <span class="diag-cat-hint">依赖电子病历系统对接，当前版本暂未开放</span>
    </div>
</div>

<script>
var _diagCats = [['emergency', '门急诊诊断'], ['admission', '入院诊断'], ['discharge', '出院诊断'], ['pathology', '病理诊断'], ['external', '损伤、中毒外部原因']];
var _diagCatsMore = [['nosocomial', '院内感染'], ['complication', '手术并发症'], ['death_cause', '并发症死亡根本原因']];
var _diagShowMore = false;
var _diagActive = 'emergency';
var _diagRows = [];
var _diagTemplates = [];

async function emr_diagnosis_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    IcdSearch.init('diag-input', 'diag-code');
    emr_diagnosis_load();
}

function emr_diagnosis_renderTabs() {
    const cats = _diagCats.concat(_diagShowMore ? _diagCatsMore : []);
    const tabs = cats.map(([k, label]) =>
        `<button class="tab${_diagActive === k ? ' active' : ''}" onclick="emr_diagnosis_select('${k}')">${label}</button>`);
    tabs.push(`<button class="tab" onclick="emr_diagnosis_toggleMore()">${_diagShowMore ? '收起分类' : '更多分类'}</button>`);
    document.getElementById('diag-tabs').innerHTML = tabs.join('');
    document.getElementById('diag-sync-hint').classList.toggle('hidden', _diagActive !== 'discharge');
}

function emr_diagnosis_select(k) {
    _diagActive = k;
    emr_diagnosis_renderTabs();
    emr_diagnosis_renderList();
}

function emr_diagnosis_toggleMore() {
    _diagShowMore = !_diagShowMore;
    if (!_diagShowMore && _diagCatsMore.some(c => c[0] === _diagActive)) _diagActive = 'emergency';
    emr_diagnosis_renderTabs();
    emr_diagnosis_renderList();
}

function emr_diagnosis_load() {
    const p = PatientContext.current();
    if (!p) return;
    Promise.all([
        AjaxLoader.api('emr/diagnosis-list?patient_id=' + p.id, { silent: true }),
        AjaxLoader.api('emr/diagnosis-template-list', { silent: true })
    ]).then(([dr, tr]) => {
        _diagRows = dr.data || [];
        _diagTemplates = tr.data || [];
        emr_diagnosis_renderTabs();
        emr_diagnosis_renderList();
        emr_diagnosis_renderTemplates();
    }).catch(() => {});
}

function emr_diagnosis_renderList() {
    const label = (_diagCats.concat(_diagCatsMore).find(c => c[0] === _diagActive) || ['', ''])[1];
    const rows = _diagRows.filter(r => r.category === _diagActive);
    document.getElementById('diag-list-title').textContent = label + '（' + rows.length + '）';
    const tbody = document.getElementById('diag-list-body');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state">该分类暂无诊断</div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td>${Format.escape(r.name)}</td>
        <td>${Format.escape(r.icd_code || '')}</td>
        <td>${Format.escape(r.created_by_name || '')}</td>
        <td>${Format.escape(r.created_at || '')}</td>
        <td><button class="btn btn-small btn-danger" onclick="emr_diagnosis_delete(${r.id})">删除</button></td>
    </tr>`).join('');
}

function emr_diagnosis_renderTemplates() {
    const box = document.getElementById('diag-tpl-box');
    if (!_diagTemplates.length) { box.innerHTML = ''; return; }
    box.innerHTML = '<div class="apply-panels-title">常用诊断</div>' + _diagTemplates.map(t => `
        <span class="apply-panel-chip">
            <a onclick="emr_diagnosis_useTemplate(${t.id})">${Format.escape(t.name)}</a>
            <button type="button" title="删除模板" onclick="emr_diagnosis_deleteTemplate(${t.id})">×</button>
        </span>`).join('');
}

function emr_diagnosis_useTemplate(id) {
    const t = _diagTemplates.find(x => x.id == id);
    if (!t) return;
    document.getElementById('diag-input').value = t.name;
    document.getElementById('diag-code').value = t.icd_code || '';
    Dom.toast('已填入「' + t.name + '」，点击「添加到当前分类」确认', 'info');
}

async function emr_diagnosis_add() {
    const p = PatientContext.current();
    if (!p) return;
    const name = (document.getElementById('diag-input').value || '').trim();
    const code = (document.getElementById('diag-code').value || '').trim();
    if (!name) { Dom.toast('请输入或选择诊断', 'error'); return; }
    const saveTpl = document.getElementById('diag-save-tpl').checked;
    await AjaxLoader.api('emr/diagnosis-save', { method: 'POST', data: {
        patient_id: p.id, category: _diagActive, name, icd_code: code
    }});
    if (saveTpl) {
        try {
            await AjaxLoader.api('emr/diagnosis-template-save', { method: 'POST', data: { name, icd_code: code, category: _diagActive } });
        } catch (e) { /* 模板重复等不阻断添加 */ }
    }
    Dom.toast('诊断已添加', 'success');
    document.getElementById('diag-input').value = '';
    document.getElementById('diag-code').value = '';
    document.getElementById('diag-save-tpl').checked = false;
    emr_diagnosis_load();
}

function emr_diagnosis_delete(id) {
    Dom.modal('删除诊断', '<p>删除该诊断？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('emr/diagnosis-delete', { method: 'POST', data: { id } });
            Dom.toast('已删除', 'success');
            emr_diagnosis_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}

function emr_diagnosis_deleteTemplate(id) {
    Dom.modal('删除常用诊断', '<p>删除该常用诊断模板？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('emr/diagnosis-template-delete', { method: 'POST', data: { id } });
            Dom.toast('已删除', 'success');
            emr_diagnosis_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}
</script>
