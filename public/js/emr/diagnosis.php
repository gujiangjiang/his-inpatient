<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">诊断</h3>
        <p class="page-desc">患者诊断分类管理、诊断同步与诊断符合情况（点击行内「＋」添加）</p>
    </div>
    <div>
        <button class="btn" onclick="emr_diagnosis_sync('discharge')">出院诊断同步</button>
        <button class="btn" onclick="emr_diagnosis_sync('admission')">入院诊断同步</button>
        <button class="btn" onclick="emr_diagnosis_load()">刷新</button>
    </div>
</div>

<div class="card">
    <div class="card-header">常用诊断 <span class="fs-12 text-muted">点击模板可打开添加窗并自动填入</span></div>
    <div class="apply-panels" id="diag-tpl-box"></div>
</div>

<div class="diag-layout">
    <div class="card diag-left" id="diag-rows"></div>
    <div class="card diag-right">
        <div class="card-header">诊断符合情况</div>
        <div id="diag-match-box"></div>
    </div>
</div>

<div class="diag-pop hidden" id="diag-pop">
    <input class="form-control" id="diag-pop-kw" placeholder="搜索诊断 / ICD-10 编码 / 拼音首字母"
        oninput="emr_diag_pop_search(this.value)" onkeydown="emr_diag_pop_key(event)">
    <div class="diag-pop-res" id="diag-pop-res"></div>
    <div class="diag-pop-form">
        <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">已选诊断</label>
            <div id="diag-pop-sel" class="diag-pop-empty">搜索并选择诊断，或直接输入名称「保存」</div>
        </div>
        <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">分类</label>
            <select id="diag-pop-cat" class="form-control" data-spicker="off"></select>
        </div>
        <div class="form-group" style="margin-bottom:8px">
            <label class="form-label">备注（选填）</label>
            <input id="diag-pop-note" class="form-control">
        </div>
        <div class="flex-between">
            <label class="fs-13"><input type="checkbox" id="diag-pop-tpl"> 存为常用</label>
            <div>
                <button class="btn btn-small" onclick="emr_diag_pop_close()">取消</button>
                <button class="btn btn-small btn-primary" onclick="emr_diag_pop_save()">保存</button>
            </div>
        </div>
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
var _diagPopSel = null;
var _diagPopTimer = null;
var _diagPopState = { kw: '', offset: 0, hasMore: true, loading: false, cursor: -1 };
var _diagPopScroll = null;

async function emr_diagnosis_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
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

/* ===== 添加诊断悬浮窗 (参考门诊一体化) ===== */
function emr_diag_pop_open(cat, ev, presetName, presetCode) {
    _diagPopSel = presetName ? { name: presetName, code: presetCode || '' } : null;
    _diagPopState = { kw: '', offset: 0, hasMore: true, loading: false, cursor: -1 };
    if (_diagPopScroll) ScrollLoad.reset(_diagPopScroll);
    const pop = document.getElementById('diag-pop');
    pop.classList.remove('hidden');
    document.getElementById('diag-pop-cat').value = cat || 'initial';
    document.getElementById('diag-pop-kw').value = '';
    document.getElementById('diag-pop-note').value = '';
    document.getElementById('diag-pop-tpl').checked = false;
    document.getElementById('diag-pop-res').innerHTML = '';
    emr_diag_pop_renderSel();
    emr_diag_pop_position(ev);
    setTimeout(() => document.getElementById('diag-pop-kw').focus(), 30);
}

function emr_diag_pop_position(ev) {
    const pop = document.getElementById('diag-pop');
    const w = 320, h = 420;
    let x = (ev && ev.clientX) ? ev.clientX - 24 : (window.innerWidth - w) / 2;
    let y = (ev && ev.clientY) ? ev.clientY + 6 : 120;
    x = Math.min(Math.max(8, x), window.innerWidth - w - 8);
    if (y + h > window.innerHeight - 8) y = Math.max(8, window.innerHeight - h - 8);
    pop.style.left = x + 'px';
    pop.style.top = y + 'px';
}

function emr_diag_pop_search(v) {
    _diagPopState.kw = (v || '').trim();
    const res = document.getElementById('diag-pop-res');
    if (_diagPopState.kw.length < 1) { res.innerHTML = ''; return; }
    clearTimeout(_diagPopTimer);
    _diagPopTimer = setTimeout(function() {
        if (_diagPopScroll) ScrollLoad.reset(_diagPopScroll);
        emr_diag_pop_loadMore(true);
    }, 250);
}

/* 分页加载 (滚动触底续载, 键盘导航联动) */
function emr_diag_pop_loadMore(reset) {
    const st = _diagPopState;
    if (st.loading || !st.kw) return Promise.resolve(false);
    if (!reset && !st.hasMore) return Promise.resolve(false);
    st.loading = true;
    const res = document.getElementById('diag-pop-res');
    if (reset) { st.offset = 0; st.hasMore = true; st.cursor = -1; res.innerHTML = ''; }
    return AjaxLoader.api('emr/icd-search?keyword=' + encodeURIComponent(st.kw) + '&limit=20&offset=' + st.offset, { silent: true })
        .then(function(r) {
            st.loading = false;
            const rows = r.data || [];
            if (reset && !rows.length) {
                res.innerHTML = '<div class="diag-pop-item diag-pop-none">无匹配，回车按输入文本直接保存</div>';
                st.hasMore = false;
                return false;
            }
            res.insertAdjacentHTML('beforeend', rows.map(function(x) {
                return '<div class="diag-pop-item" data-code="' + Format.escape(x.code) + '" data-name="' + Format.escape(x.name) + '">' +
                    '<span class="dp-code">' + Format.escape(x.code) + '</span>' +
                    '<span class="dp-name">' + Format.escape(x.name) + '</span></div>';
            }).join(''));
            res.querySelectorAll('.diag-pop-item[data-code]').forEach(function(el) {
                if (el._bound) return;
                el._bound = true;
                el.addEventListener('click', function() { emr_diag_pop_pick(el.dataset.code, el.dataset.name); });
            });
            st.offset += rows.length;
            const total = r.total != null ? Number(r.total) : 0;
            st.hasMore = rows.length >= 20 && (total === 0 || st.offset < total);
            ScrollLoad.check(_diagPopScroll);
            return st.hasMore;
        }).catch(function() { st.loading = false; return false; });
}

/* 键盘上下移动候选 */
function emr_diag_pop_moveCursor(delta) {
    const items = Array.from(document.querySelectorAll('#diag-pop-res .diag-pop-item[data-code]'));
    if (!items.length) return;
    const st = _diagPopState;
    const base = st.cursor < 0 ? (delta > 0 ? -1 : 0) : st.cursor;
    st.cursor = Math.max(0, Math.min(items.length - 1, base + delta));
    items.forEach(function(el, i) { el.classList.toggle('cursor', i === st.cursor); });
    if (items[st.cursor]) items[st.cursor].scrollIntoView({ block: 'nearest' });
}

function emr_diag_pop_pick(code, name) {
    _diagPopSel = { name: name, code: code };
    _diagPopState.kw = '';
    _diagPopState.cursor = -1;
    document.getElementById('diag-pop-kw').value = '';
    document.getElementById('diag-pop-res').innerHTML = '';
    emr_diag_pop_renderSel();
}

function emr_diag_pop_renderSel() {
    const box = document.getElementById('diag-pop-sel');
    if (!_diagPopSel) {
        box.innerHTML = '搜索并选择诊断，或直接输入名称「保存」';
        box.classList.add('diag-pop-empty');
        return;
    }
    box.classList.remove('diag-pop-empty');
    box.innerHTML = `<b>[${Format.escape(_diagPopSel.code || '-')}]</b> ${Format.escape(_diagPopSel.name)}`;
}

function emr_diag_pop_key(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        emr_diag_pop_moveCursor(1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        emr_diag_pop_moveCursor(-1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const items = document.querySelectorAll('#diag-pop-res .diag-pop-item[data-code]');
        const st = _diagPopState;
        if (st.cursor >= 0 && items[st.cursor]) {
            emr_diag_pop_pick(items[st.cursor].dataset.code, items[st.cursor].dataset.name);
        } else if (items.length === 1) {
            emr_diag_pop_pick(items[0].dataset.code, items[0].dataset.name);
        } else {
            emr_diag_pop_save();
        }
    }
}

async function emr_diag_pop_save() {
    const p = PatientContext.current();
    if (!p) return;
    let sel = _diagPopSel;
    const freeText = document.getElementById('diag-pop-kw').value.trim();
    if (!sel && freeText) sel = { name: freeText, code: '' };
    if (!sel) { Dom.toast('请先搜索选择或输入诊断', 'error'); return; }
    const cat = document.getElementById('diag-pop-cat').value;
    const note = document.getElementById('diag-pop-note').value.trim();
    const saveTpl = document.getElementById('diag-pop-tpl').checked;
    await AjaxLoader.api('emr/diagnosis-save', { method: 'POST', data: {
        patient_id: p.id, category: cat, name: sel.name, icd_code: sel.code, note: note
    }});
    if (saveTpl) {
        try {
            await AjaxLoader.api('emr/diagnosis-template-save', { method: 'POST', data: { name: sel.name, icd_code: sel.code, category: cat } });
        } catch (e2) { /* 模板重复等不阻断 */ }
    }
    Dom.toast('诊断已添加', 'success');
    emr_diag_pop_close();
    emr_diagnosis_load();
}

function emr_diag_pop_close() {
    document.getElementById('diag-pop').classList.add('hidden');
    _diagPopSel = null;
}
</script>
