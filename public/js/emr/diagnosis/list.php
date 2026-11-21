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
var _diagPopSel = null;
var _diagPopTimer = null;
var _diagPopState = { kw: '', offset: 0, hasMore: true, loading: false, cursor: -1 };
var _diagPopScroll = null;

async function emr_diagnosis_init() {
    await App.mountShell('doctor');
    document.getElementById('diag-pop-cat').innerHTML = _diagCats.map(([k, label]) =>
        `<option value="${k}">${label}</option>`).join('');
    _diagPopScroll = ScrollLoad.attach(document.getElementById('diag-pop-res'), function() {
        return emr_diag_pop_loadMore(false);
    }, { autoCheck: false });
    document.addEventListener('mousedown', function(e) {
        const pop = document.getElementById('diag-pop');
        if (pop && !pop.classList.contains('hidden') && !pop.contains(e.target) && !e.target.closest('.diag-plus')) {
            emr_diag_pop_close();
        }
    });
    document.addEventListener('keydown', function(e) {
        const pop = document.getElementById('diag-pop');
        if (e.key === 'Escape' && pop && !pop.classList.contains('hidden')) {
            e.stopPropagation();
            emr_diag_pop_close();
        }
    });
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
            <button class="diag-plus" title="添加到该分类" onclick="emr_diag_pop_open('${k}', event)">＋</button>
        </div>`;
    }).join('');
}

function emr_diagnosis_renderTemplates() {
    const box = document.getElementById('diag-tpl-box');
    if (!_diagTemplates.length) {
        box.innerHTML = '<div class="empty-state">暂无常用诊断（添加时可勾选「存为常用」）</div>';
        return;
    }
    box.innerHTML = _diagTemplates.map(t => `
        <span class="apply-panel-chip">
            <a data-tpl-id="${t.id}">${Format.escape(t.name)}</a>
            <button type="button" title="删除模板" onclick="emr_diagnosis_deleteTemplate(${t.id})">×</button>
        </span>`).join('');
    box.querySelectorAll('a[data-tpl-id]').forEach(el => {
        el.addEventListener('click', () => {
            const t = _diagTemplates.find(x => x.id == el.dataset.tplId);
            if (t) emr_diag_pop_open('', { clientX: el.getBoundingClientRect().left, clientY: el.getBoundingClientRect().bottom }, t.name, t.icd_code);
        });
    });
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
    Dom.confirm('删除诊断', '删除该诊断？', async function() {
            await AjaxLoader.api('emr/diagnosis-delete', { method: 'POST', data: { id: id } });
            Dom.toast('已删除', 'success');
            emr_diagnosis_load();
        }, { danger: true, okText: '删除' });
}

function emr_diagnosis_deleteTemplate(id) {
    Dom.confirm('删除常用诊断', '删除该常用诊断模板？', async function() {
            await AjaxLoader.api('emr/diagnosis-template-delete', { method: 'POST', data: { id: id } });
            Dom.toast('已删除', 'success');
            emr_diagnosis_load();
        }, { danger: true, okText: '删除' });
}

</script>
