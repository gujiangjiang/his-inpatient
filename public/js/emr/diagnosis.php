<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">诊断</h3>
        <p class="page-desc">患者诊断分类管理、诊断同步与诊断符合情况</p>
    </div>
    <div>
        <button class="btn" onclick="emr_diagnosis_sync('discharge')">出院诊断同步</button>
        <button class="btn" onclick="emr_diagnosis_sync('admission')">入院诊断同步</button>
        <button class="btn" onclick="emr_diagnosis_load()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="diag-add">
        <div class="diag-add-input">
            <input type="text" id="diag-input" class="form-control" placeholder="搜索诊断 / ICD-10 编码 / 拼音首字母（门诊标准编码库）" autocomplete="off">
            <input type="hidden" id="diag-code">
        </div>
        <select id="diag-category" class="form-control diag-cat-select"></select>
        <label class="fs-13" style="white-space:nowrap"><input type="checkbox" id="diag-save-tpl"> 存为常用诊断</label>
        <button class="btn btn-primary" onclick="emr_diagnosis_add()">添加</button>
    </div>
    <div class="apply-panels" id="diag-tpl-box"></div>
</div>

<div class="diag-layout">
    <div class="card diag-left" id="diag-rows"></div>
    <div class="card diag-right">
        <div class="card-header">诊断符合情况</div>
        <div id="diag-match-box"></div>
    </div>
</div>

<script>
var _diagCats = [
    ['emergency', '门(急)诊诊断'], ['admission', '入院诊断'], ['discharge', '出院诊断'],
    ['pathology', '病理诊断'], ['external', '损伤、中毒的外部原因'],
    ['nosocomial', '院内感染'], ['complication', '手术并发症'], ['death_cause', '死亡根本原因'],
    ['initial', '初步诊断'], ['corrected', '修正诊断'], ['supplement', '补充诊断']
];
var _diagMatch = [
    ['out_in', '1、门诊诊断与出院诊断'], ['adm_out', '2、入院诊断与出院诊断'],
    ['pre_post', '3、术前诊断与术后诊断'], ['rad_post', '4、放射诊断与术后诊断'],
    ['clin_path', '5、临床诊断与病理诊断'], ['rad_path', '6、放射诊断与病理诊断'],
    ['clin_autopsy', '7、临床诊断与尸检诊断'], ['tcm_out', '8、中医门诊诊断与出院诊断'],
    ['tcm_adm_out', '9、中医入院诊断与出院诊断']
];
var _diagMatchOpts = [['', '请选择'], ['0', '未做'], ['1', '符合'], ['2', '不符合'], ['3', '不肯定']];
var _diagRows = [];
var _diagTemplates = [];
var _diagMatchData = {};

async function emr_diagnosis_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    document.getElementById('diag-category').innerHTML = _diagCats.map(([k, label]) =>
        `<option value="${k}">${label}</option>`).join('');
    IcdSearch.init('diag-input', 'diag-code');
    emr_diagnosis_load();
}

function emr_diagnosis_load() {
    const p = PatientContext.current();
    if (!p) return;
    Promise.all([
        AjaxLoader.api('emr/diagnosis-list?patient_id=' + p.id, { silent: true }),
        AjaxLoader.api('emr/diagnosis-template-list', { silent: true }),
        AjaxLoader.api('emr/diagnosis-match-get?patient_id=' + p.id, { silent: true })
    ]).then(([dr, tr, mr]) => {
        _diagRows = dr.data || [];
        _diagTemplates = tr.data || [];
        _diagMatchData = mr.data || {};
        emr_diagnosis_renderRows();
        emr_diagnosis_renderTemplates();
        emr_diagnosis_renderMatch();
    }).catch(() => {});
}

function emr_diagnosis_renderRows() {
    document.getElementById('diag-rows').innerHTML = _diagCats.map(([k, label]) => {
        const items = _diagRows.filter(r => r.category === k);
        const content = items.length
            ? items.map((r, i) => `<span class="diag-item">[${Format.escape(r.icd_code || '-')}] ${Format.escape(r.name)}
                <i class="diag-idx">${i + 1}</i>
                <button class="diag-del" title="删除" onclick="emr_diagnosis_delete(${r.id})">×</button></span>`).join('')
            : '<span class="diag-empty">—</span>';
        return `<div class="diag-row">
            <div class="diag-label">${label}</div>
            <div class="diag-content">${content}</div>
            <button class="diag-plus" title="添加到该分类" onclick="emr_diagnosis_focus('${k}')">＋</button>
        </div>`;
    }).join('');
}

function emr_diagnosis_focus(k) {
    document.getElementById('diag-category').value = k;
    document.getElementById('diag-input').focus();
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
    Dom.toast('已填入「' + t.name + '」，点击「添加」确认', 'info');
}

function emr_diagnosis_renderMatch() {
    document.getElementById('diag-match-box').innerHTML = _diagMatch.map(([k, label]) => `
        <div class="diag-match-row">
            <span class="diag-match-label">${label}</span>
            <select class="diag-match-select" style="width:112px" data-key="${k}"
                onchange="emr_diagnosis_saveMatch('${k}', this.value)">
                ${_diagMatchOpts.map(([v, t]) => `<option value="${v}" ${(_diagMatchData[k] || '') === v ? 'selected' : ''}>${t}</option>`).join('')}
            </select>
        </div>`).join('');
    if (window.SelectPicker) SelectPicker.initInputs(document.getElementById('diag-match-box'));
}

async function emr_diagnosis_saveMatch(key, value) {
    const p = PatientContext.current();
    if (!p) return;
    await AjaxLoader.api('emr/diagnosis-match-save', { method: 'POST', data: { patient_id: p.id, mkey: key, mvalue: value } });
    Dom.toast('诊断符合情况已保存', 'success');
}

async function emr_diagnosis_add() {
    const p = PatientContext.current();
    if (!p) return;
    const name = (document.getElementById('diag-input').value || '').trim();
    const code = (document.getElementById('diag-code').value || '').trim();
    const category = document.getElementById('diag-category').value;
    if (!name) { Dom.toast('请输入或选择诊断', 'error'); return; }
    const saveTpl = document.getElementById('diag-save-tpl').checked;
    await AjaxLoader.api('emr/diagnosis-save', { method: 'POST', data: {
        patient_id: p.id, category: category, name: name, icd_code: code
    }});
    if (saveTpl) {
        try {
            await AjaxLoader.api('emr/diagnosis-template-save', { method: 'POST', data: { name: name, icd_code: code, category: category } });
        } catch (e) { /* 模板重复等不阻断添加 */ }
    }
    Dom.toast('诊断已添加', 'success');
    document.getElementById('diag-input').value = '';
    document.getElementById('diag-code').value = '';
    document.getElementById('diag-save-tpl').checked = false;
    emr_diagnosis_load();
}

function emr_diagnosis_sync(mode) {
    const p = PatientContext.current();
    if (!p) return;
    const label = mode === 'admission' ? '门(急)诊诊断 → 入院诊断' : '入院诊断 → 出院诊断';
    Dom.modal('诊断同步', `<p>将「${label}」中尚未同步的诊断复制到目标分类，是否继续？</p>`, [
        { text: '同步', type: 'primary', onclick: async function() {
            const r = await AjaxLoader.api('emr/diagnosis-sync', { method: 'POST', data: { patient_id: p.id, mode: mode } });
            if (r.status === 'success') {
                Dom.toast(r.message || '已同步', 'success');
                emr_diagnosis_load();
            }
        } },
        { text: '取消', type: 'default' }
    ]);
}

function emr_diagnosis_delete(id) {
    Dom.modal('删除诊断', '<p>删除该诊断？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('emr/diagnosis-delete', { method: 'POST', data: { id: id } });
            Dom.toast('已删除', 'success');
            emr_diagnosis_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}

function emr_diagnosis_deleteTemplate(id) {
    Dom.modal('删除常用诊断', '<p>删除该常用诊断模板？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('emr/diagnosis-template-delete', { method: 'POST', data: { id: id } });
            Dom.toast('已删除', 'success');
            emr_diagnosis_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}
</script>
