<script>
/** 质控提醒弹窗 */
function emr_showQc(catId) {
    const items = _emrQc.filter(q => q.cat == catId);
    if (!items.length) { Dom.toast('暂无质控提醒', 'success'); return; }
    const cat = _emrCategories.find(c => c.id == catId);
    const html = '<div class="emr-qc-list">' + items.map(q =>
        '<div class="emr-qc-item"><span class="emr-qc-tag">质控</span><span>' + Format.escape(q.msg) + '</span></div>').join('') + '</div>'
        + '<p class="emr-qc-hint">完成对应文书的创建并签名后，该提醒自动消失。</p>';
    Dom.modal((cat ? cat.name : '') + ' · 质控提醒', html, [{ text: '知道了', type: 'primary' }]);
}

/** 一键同步诊断 (来源: 左侧「诊断」模块):
 *  出院诊断 → discharge 分类; 其余 → 优先级口径 修正 > 入院 > 初步 (不含门急诊)
 *  三类全空时给出完善诊断的引导提示 */
async function fetchAdmissionDiagText(dynKey) {
    if (dynKey === 'diag_discharge') {
        const text = await emrDiagTextOf('discharge');
        if (!text) {
            Dom.toast('诊断中暂无出院诊断，请先在左侧「诊断」中添加', 'error');
            return null;
        }
        return text;
    }
    const info = await emrDiagRefInfo();
    if (!info) {
        Dom.toast('暂无初步诊断/入院诊断/修正诊断，请先到左侧「诊断」完善后再刷新', 'error');
        return null;
    }
    return info.text;
}

/** 状态栏保存状态 (自动保存静默进行, 不再全屏加载遮罩) */
function setSaveState(text, cls) {
    const el = document.getElementById('emr-save-state');
    if (!el) return;
    el.textContent = text ? '· ' + text : '';
    el.className = 'emr-sb-state ' + (cls || '');
    el.title = cls === 'error' ? (el.dataset.msg || '') : '';
}

function updateEditorStatus(chars, dirty) {
    const tag = document.getElementById('emr-sb-savetag');
    if (tag) { tag.textContent = dirty ? '未保存' : '已保存'; tag.className = dirty ? 'emr-sb-dirty' : 'emr-sb-saved'; }
    const cnt = document.getElementById('emr-sb-count');
    if (cnt) cnt.textContent = '字数 ' + chars;
}

function syncToolbar(signed) {
    ['btn-bold', 'btn-italic', 'btn-underline', 'btn-strike',
     'btn-align-l', 'btn-align-c', 'btn-align-r', 'btn-align-j',
     'btn-undo', 'btn-redo', 'btn-save'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = signed;
    });
    document.getElementById('btn-sign').disabled = signed;
    document.getElementById('btn-print').disabled = false;
    document.getElementById('btn-versions').disabled = false;
    const statusEl = document.getElementById('emr-doc-status');
    statusEl.textContent = signed ? '已签名 · 只读锁定'
        : (_emrReviseMode ? '修订中 · 删除留痕（打印不输出）' : '草稿 · 可编辑');
    statusEl.className = 'emr-doc-status ' + (signed ? 'signed' : 'draft') + (_emrReviseMode ? ' revise' : '');
}

/* ================= 保存 ================= */
async function emr_saveDraft(auto) {
    if (!_emrCurrentDoc) return;
    if (_emrCurrentDoc.status === 'signed') { if (!auto) Dom.toast('文书已签名锁定, 禁止修改', 'error'); return; }
    if (!_emrEditor) { if (!auto) Dom.toast('编辑器未就绪', 'error'); return; }
    clearTimeout(_emrAutoSaveTimer);
    const isAuto = !!auto;

    const title = _emrCurrentDoc.title || '病历';
    const doc = _emrEditor.getDoc();
    doc.title = title;
    /* 自动计算项校验: 公式涉及的字段代码在当前文书中必须存在 (缺失直接报错, 不落库) */
    const missCalc = (window.EmrCalc && EmrCalc.missing(doc.body)) || [];
    if (missCalc.length) {
        const msg = '自动计算项缺少字段: ' + missCalc.map(function (m) { return m.name + '（' + m.keys.join('、') + '）'; }).join('；');
        setSaveState('保存失败', 'error');
        if (!isAuto) Dom.toast(msg, 'error');
        return;
    }
    const meta = Object.assign({}, _emrCurrentDoc.doc_meta || {});
    if (!meta.guard) meta.guard = EmrDoc.collectGuard(doc.body);

    let contentHtml = '';
    try {
        contentHtml = _emrEditor.buildPrintHTML();
    } catch (e) {
        console.error('打印快照生成失败', e);
        Dom.toast('打印快照生成失败: ' + (e && e.message ? e.message : e), 'error');
    }

    setSaveState('保存中…', 'saving');
    try {
        const r = await AjaxLoader.api('emr/document-save', { method: 'POST', data: {
            id: _emrCurrentDoc.id,
            patient_id: _emrPatient.id,
            category_id: _emrCurrentDoc.category_id,
            template_id: _emrCurrentDoc.template_id,
            title: title,
            doc_meta: meta,
            doc_json: JSON.stringify(doc),
            content_html: contentHtml
        }, silent: true }); /* 静默保存: 不弹全屏加载遮罩, 状态栏可见 */
        _emrCurrentDoc.id = r.data.id;
        _emrCurrentDoc.doc_meta = meta;
        _emrEditor.markSaved();
        setSaveState('已保存 ' + Format.nowStamp().slice(11), 'saved');
        if (!isAuto) Dom.toast('草稿已保存', 'success');
        refreshDocs();
    } catch (e) {
        console.error('保存失败', e);
        const el = document.getElementById('emr-save-state');
        if (el) el.dataset.msg = e && e.message || '';
        setSaveState('保存失败', 'error');
        if (!isAuto) Dom.toast('保存失败: ' + (e && e.message || e), 'error');
    }
}

function scheduleAutoSave() {
    if (!_emrEditMode || !_emrEditor || !_emrCurrentDoc || _emrCurrentDoc.status === 'signed') return;
    clearTimeout(_emrAutoSaveTimer);
    _emrAutoSaveTimer = setTimeout(() => emr_saveDraft(true), 8000);
}

</script>
