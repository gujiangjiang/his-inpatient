<script>
/* ===== 添加诊断悬浮窗 ===== */
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
