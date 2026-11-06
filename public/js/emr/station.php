<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree">
        <div class="emr-tree-header">病历文书</div>
        <div id="emr-category-tree" class="emr-category-tree"></div>
    </div>
    <!-- 二级主视图: 字处理病历编辑区 -->
    <div class="emr-editor-pane">
        <div id="emr-editor-empty" class="emr-editor-empty">
            <p>请从左侧选择病历文书，或点击分类后的 <b>＋</b> 从模板新建</p>
        </div>
        <div id="emr-editor" class="emr-editor" style="display:none">
            <div class="emr-toolbar">
                <button type="button" class="btn btn-small fmt-btn fmt-b" id="btn-bold" title="加粗">B</button>
                <button type="button" class="btn btn-small fmt-btn fmt-i" id="btn-italic" title="斜体">I</button>
                <button type="button" class="btn btn-small fmt-btn fmt-u" id="btn-underline" title="下划线">U</button>
                <button type="button" class="btn btn-small fmt-btn fmt-s" id="btn-strike" title="删除线">S</button>
                <span class="emr-tb-sep"></span>
                <button type="button" class="btn btn-small" id="btn-align-l" title="左对齐">左</button>
                <button type="button" class="btn btn-small" id="btn-align-c" title="居中">中</button>
                <button type="button" class="btn btn-small" id="btn-align-r" title="右对齐">右</button>
                <button type="button" class="btn btn-small" id="btn-align-j" title="两端对齐">两</button>
                <span class="emr-tb-sep"></span>
                <button type="button" class="btn btn-small" id="btn-undo" title="撤销 (Ctrl+Z)">撤销</button>
                <button type="button" class="btn btn-small" id="btn-redo" title="重做 (Ctrl+Y)">重做</button>
                <span class="emr-tb-spacer"></span>
                <button type="button" class="btn btn-small btn-success" id="btn-save">保存草稿</button>
                <button type="button" class="btn btn-small" id="btn-print">打印</button>
                <button type="button" class="btn btn-small" id="btn-template">存为模板</button>
                <button type="button" class="btn btn-small" id="btn-versions">版本</button>
                <button type="button" class="btn btn-small btn-primary" id="btn-sign">医师签名</button>
                <span id="emr-doc-status" class="emr-doc-status"></span>
            </div>
            <div class="emr-doc-header">
                <input type="text" id="emr-doc-title" class="emr-doc-title-input" placeholder="文书标题">
            </div>
            <div id="emr-editor-scroll" class="emr-editor-scroll">
                <div class="emr-editor-page" id="emr-editor-page">
                    <div id="emr-editor-canvas"></div>
                </div>
            </div>
            <div id="emr-editor-status" class="emr-statusbar"><span class="emr-sb-saved">已保存</span></div>
            <div id="emr-signature-footer" class="emr-signature-footer" style="display:none"></div>
            <div id="emr-doc-sections" class="emr-doc-sections" style="display:none"></div>
        </div>
    </div>
</div>

<script>
var _emrCategories = [];
var _emrDocs = [];
var _emrCurrentDoc = null;
var _emrEditMode = true;
var _emrEditor = null;       // EmrEditor 实例 (word 模式)
var _emrSectionsHist = [];   // 旧结构化模式撤销栈
var _emrSectionsHistIdx = -1;
var _emrAutoSaveTimer = null;
var _emrPatient = null;

/* ================= 初始化 / 树 ================= */
async function emr_station_init() {
    _emrPatient = PatientContext.current();
    if (!_emrPatient) { Dom.toast('请先选择患者', 'error'); return; }
    await loadCategories();
    await loadDocuments();
    renderCategoryTree();
}

async function loadCategories() {
    const r = await AjaxLoader.api('emr/category-list', { silent: true });
    _emrCategories = r.data || [];
}

async function loadDocuments() {
    const r = await AjaxLoader.api('emr/document-list?patient_id=' + _emrPatient.id, { silent: true });
    _emrDocs = r.data || [];
}

function renderCategoryTree() {
    const container = document.getElementById('emr-category-tree');
    container.innerHTML = '';
    _emrCategories.forEach(cat => {
        const docs = _emrDocs.filter(d => d.category_id == cat.id);
        const group = document.createElement('div');
        group.className = 'emr-cat-group';
        const header = document.createElement('div');
        header.className = 'emr-cat-header';
        header.innerHTML = '<span class="emr-cat-name">' + Format.escape(cat.name) + '</span>' +
            '<span class="emr-cat-count">' + docs.length + '</span>' +
            '<span class="emr-cat-add" title="从模板新建" onclick="emr_openTemplatePicker(' + cat.id + ')">＋</span>';
        group.appendChild(header);
        const docList = document.createElement('div');
        docList.className = 'emr-doc-list';
        docs.forEach(d => {
            const item = document.createElement('div');
            item.className = 'emr-doc-item' + (d.status === 'signed' ? ' signed' : '');
            item.innerHTML = '<span class="emr-doc-title-text">' + Format.escape(d.title || d.template_name || '未命名文书') + '</span>' +
                '<span class="emr-doc-status-tag ' + (d.status === 'signed' ? 'signed' : 'draft') + '">' + (d.status === 'signed' ? '已签名' : '草稿') + '</span>';
            item.onclick = () => emr_openDocument(d.id);
            item.oncontextmenu = (e) => { e.preventDefault(); emr_showContextMenu(e, d); };
            docList.appendChild(item);
        });
        group.appendChild(docList);
        container.appendChild(group);
    });
}

/* ================= 模板 → 新建 ================= */
async function emr_openTemplatePicker(categoryId) {
    const r = await AjaxLoader.api('emr/template-list?category_id=' + categoryId + '&structured=1', { silent: true });
    const templates = r.data || [];
    if (templates.length === 0) { Dom.toast('该分类下暂无可用模板', 'error'); return; }
    let options = '<option value="">请选择模板</option>';
    templates.forEach(t => {
        options += '<option value="' + t.id + '">' + Format.escape(t.name) + '</option>';
    });
    const content = '<div class="form-group"><label class="form-label">选择模板</label><select id="template-pick" class="form-control">' + options + '</select></div>';
    Dom.modal('病历模板选择器', content, [
        { text: '确定', type: 'primary', onclick: function() {
            const tid = document.getElementById('template-pick').value;
            if (!tid) { Dom.toast('请选择模板', 'error'); return; }
            emr_createFromTemplate(tid, categoryId);
        } },
        { text: '取消', type: 'default' }
    ]);
}

async function emr_createFromTemplate(templateId, categoryId) {
    const tr = await AjaxLoader.api('emr/template-get?id=' + templateId, { silent: true });
    const template = tr.data;
    if (!template) { Dom.toast('模板数据异常', 'error'); return; }
    const schema = parseSchema(template.schema_json);
    if (!schema) { Dom.toast('模板 schema 异常', 'error'); return; }

    const data = { patient_id: _emrPatient.id, category_id: categoryId, template_id: templateId, title: template.name, doc_meta: {} };
    if (schema.mode === 'word') {
        const doc = EmrDoc.fromSchema(schema);
        doc.title = template.name;
        data.doc_json = JSON.stringify(doc);
        data.content_html = EmrPrint.buildHTML(doc, buildPatientInfo());
    } else {
        data.sections = (schema.sections || []).map(s => ({
            key: s.key, title: s.title, type: s.type || 'textarea',
            required: !!s.required, slot: s.slot || '', content: ''
        }));
    }
    const r = await AjaxLoader.api('emr/document-save', { method: 'POST', data: data });
    Dom.toast('已新建草稿文书', 'success');
    await loadDocuments();
    renderCategoryTree();
    emr_openDocument(r.data.id);
}

function parseSchema(json) {
    if (!json) return null;
    try { const o = JSON.parse(json); return o && typeof o === 'object' ? o : null; } catch (e) { return null; }
}

function buildPatientInfo() {
    const p = _emrPatient || PatientContext.current();
    if (!p) return {};
    const age = p.birth_date ? Math.max(0, new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
    return {
        name: p.name || '', gender: Format.gender(p.gender), age: String(age),
        admission_no: p.admission_no || '', ward_name: p.ward_name || '', bed_no: p.bed_no || ''
    };
}

function applyPageCss(page) {
    const el = document.getElementById('emr-editor-page');
    const p = page || {};
    const px = (v, dflt) => ((v == null ? dflt : v) * 96 / 72) + 'px';
    el.style.setProperty('--emr-ml', px(p.ml, 68));
    el.style.setProperty('--emr-mr', px(p.mr, 68));
    el.style.setProperty('--emr-mt', px(p.mt, 62));
    el.style.setProperty('--emr-mb', px(p.mb, 56));
    el.style.setProperty('--emr-hh', px(p.header_h, 52));
    el.style.setProperty('--emr-font', p.font || '仿宋_GB2312, FangSong, SimSun, serif');
    el.style.setProperty('--emr-size', px(p.sz, 12));
    el.style.setProperty('--emr-line', String(p.line || 1.5));
}

/* ================= 打开文书 ================= */
async function emr_openDocument(docId) {
    const r = await AjaxLoader.api('emr/document-get?id=' + docId, { silent: true });
    const doc = r.data;
    _emrCurrentDoc = doc;
    if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }
    _emrSectionsHist = []; _emrSectionsHistIdx = -1;

    document.getElementById('emr-editor-empty').style.display = 'none';
    document.getElementById('emr-editor').style.display = 'flex';
    document.getElementById('emr-doc-title').value = doc.title || '';

    const signed = doc.status === 'signed';
    if (doc.doc_json) await openWordDoc(doc, signed);
    else openSectionsDoc(doc, signed);
}

async function openWordDoc(doc, signed) {
    const schema = await loadSchema(doc.template_id);
    const guard = schema ? EmrDoc.collectGuard(schema.body || []) : null;
    applyPageCss(doc.doc_json.page);
    _emrEditMode = !signed;
    _emrEditor = EmrEditor.create({
        container: document.getElementById('emr-editor-canvas'),
        doc: doc.doc_json,
        guard: guard || EmrDoc.collectGuard(doc.doc_json.body || []),
        patientInfo: buildPatientInfo(),
        readonly: signed,
        onChange: scheduleAutoSave,
        onStatus: updateEditorStatus
    });
    syncToolbar(signed, true);
    document.getElementById('emr-doc-sections').style.display = 'none';
    document.getElementById('emr-editor-scroll').style.display = 'block';
}

async function loadSchema(templateId) {
    if (!templateId) return null;
    const r = await AjaxLoader.api('emr/template-get?id=' + templateId, { silent: true });
    return parseSchema(r.data && r.data.schema_json);
}

function updateEditorStatus(chars, dirty) {
    document.getElementById('emr-editor-status').innerHTML =
        '<span class="' + (dirty ? 'emr-sb-dirty' : 'emr-sb-saved') + '">' + (dirty ? '未保存' : '已保存') + '</span>'
        + '<span class="emr-sb-count">字数 ' + chars + '</span>';
}

function syncToolbar(signed, isWord) {
    const locked = signed || !_emrEditMode;
    ['btn-bold', 'btn-italic', 'btn-underline', 'btn-strike', 'btn-align-l', 'btn-align-c', 'btn-align-r', 'btn-align-j', 'btn-undo', 'btn-redo', 'btn-save', 'btn-template', 'btn-sign'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = locked || !isWord;
    });
    document.getElementById('btn-print').disabled = !isWord;
    document.getElementById('btn-versions').disabled = false;
    document.getElementById('btn-sign').disabled = signed;
    const statusEl = document.getElementById('emr-doc-status');
    statusEl.textContent = signed ? '已签名 · 只读锁定' : '草稿 · 可编辑';
    statusEl.className = 'emr-doc-status ' + (signed ? 'signed' : 'draft');
}

/* ================= 旧结构化文书 (兼容) ================= */
function openSectionsDoc(doc, signed) {
    _emrEditMode = !signed;
    document.getElementById('emr-editor-scroll').style.display = 'none';
    document.getElementById('emr-doc-sections').style.display = 'block';
    syncToolbar(signed, false);
    renderSections(doc, signed);
}

function renderSections(doc, signed) {
    const container = document.getElementById('emr-doc-sections');
    container.innerHTML = '';
    const sections = doc.sections || [];
    if (sections.length === 0) { container.innerHTML = '<div class="emr-section-empty">该文书无结构化段落</div>'; return; }
    sections.forEach(s => {
        const sec = document.createElement('div');
        sec.className = 'emr-section' + (s.required ? ' required' : '');
        const editable = !signed && _emrEditMode;
        let bodyHtml = '';
        if (s.type === 'textarea') {
            bodyHtml = editable
                ? '<textarea class="emr-section-input" data-key="' + Format.escape(s.key) + '" rows="4" placeholder="' + Format.escape(s.slot || '') + '">' + Format.escape(s.content || '') + '</textarea>'
                : '<div class="emr-section-render">' + Format.escape(s.content || '') + '</div>';
        } else {
            bodyHtml = editable
                ? '<input type="text" class="emr-section-input" data-key="' + Format.escape(s.key) + '" value="' + Format.escape(s.content || '') + '">'
                : '<div class="emr-section-render">' + Format.escape(s.content || '') + '</div>';
        }
        sec.innerHTML = '<div class="emr-section-title">' + Format.escape(s.title) + (s.required ? ' <span class="required-mark">*</span>' : '') + '</div>' + bodyHtml;
        container.appendChild(sec);
    });
    const sig = document.getElementById('emr-signature-footer');
    if (signed) {
        sig.style.display = 'block';
        sig.innerHTML = '医师签名：' + Format.escape(doc.signer_name || '医师') + '　' + Format.datetime(doc.signed_at);
    } else { sig.style.display = 'none'; sig.innerHTML = ''; }
}

function collectSections() {
    const sections = JSON.parse(JSON.stringify(_emrCurrentDoc.sections || []));
    document.querySelectorAll('#emr-doc-sections .emr-section-input').forEach(input => {
        const s = sections.find(x => x.key === input.dataset.key);
        if (s) s.content = input.value;
    });
    return sections;
}

function renderSectionsHtml(sections) {
    let html = '<div class="emr-print-doc">';
    sections.forEach(s => {
        html += '<div class="emr-print-section"><div class="emr-print-title">' + Format.escape(s.title) + '</div><div class="emr-print-content">' + Format.escape(s.content || '') + '</div></div>';
    });
    html += '</div>';
    return html;
}

/* ================= 保存 ================= */
async function emr_saveDraft() {
    if (!_emrCurrentDoc || _emrCurrentDoc.status === 'signed') return;
    clearTimeout(_emrAutoSaveTimer);
    const data = {
        id: _emrCurrentDoc.id,
        patient_id: _emrPatient.id,
        category_id: _emrCurrentDoc.category_id,
        template_id: _emrCurrentDoc.template_id,
        title: document.getElementById('emr-doc-title').value,
        doc_meta: _emrCurrentDoc.doc_meta
    };
    if (_emrEditor) {
        _emrEditor.getDoc().title = data.title;
        data.doc_json = JSON.stringify(_emrEditor.getDoc());
        data.content_html = _emrEditor.buildPrintHTML();
    } else {
        data.sections = collectSections();
        data.content_html = renderSectionsHtml(data.sections);
    }
    const r = await AjaxLoader.api('emr/document-save', { method: 'POST', data: data });
    _emrCurrentDoc.id = r.data.id;
    if (_emrEditor) _emrEditor.markSaved();
    else updateEditorStatus(0, false);
    Dom.toast('草稿已保存', 'success');
    loadDocuments().then(renderCategoryTree);
}

function scheduleAutoSave() {
    if (!_emrEditMode || !_emrEditor || _emrCurrentDoc.status === 'signed') return;
    clearTimeout(_emrAutoSaveTimer);
    _emrAutoSaveTimer = setTimeout(emr_saveDraft, 8000);
}

/* ================= 签名 / 打印 / 版本 / 模板 ================= */
function emr_signDoc() {
    if (!_emrCurrentDoc || _emrCurrentDoc.status === 'signed') return;
    const content = '<div class="form-group"><label class="form-label">请输入密码/工号确认身份</label><input type="password" id="sign-password" class="form-control"></div>';
    Dom.modal('医师签名', content, [
        { text: '确认签名', type: 'primary', onclick: function() {
            const pwd = document.getElementById('sign-password').value;
            if (!pwd) { Dom.toast('请输入密码', 'error'); return; }
            AjaxLoader.api('emr/document-sign', { method: 'POST', data: { id: _emrCurrentDoc.id, password: pwd } }).then(() => {
                Dom.toast('签名成功', 'success');
                emr_openDocument(_emrCurrentDoc.id);
                loadDocuments().then(renderCategoryTree);
            });
        } },
        { text: '取消', type: 'default' }
    ]);
}

function emr_printDoc() {
    if (!_emrCurrentDoc) return;
    if (_emrEditor) {
        if (_emrCurrentDoc.status === 'signed' && _emrCurrentDoc.content_html) {
            PrintHelper.printDocument(_emrCurrentDoc.content_html);
        } else {
            _emrEditor.print();
        }
        return;
    }
    const printHtml = '<html><head><title>' + Format.escape(_emrCurrentDoc.title || '病历') + '</title>' +
        '<style>.emr-print-doc{font-family:仿宋_GB2312,SimSun,serif;font-size:14px;line-height:1.5;padding:20px}.emr-print-section{margin-bottom:12px}.emr-print-title{font-weight:bold;margin-bottom:4px}.emr-print-content{text-indent:2em}</style>' +
        '</head><body>' + renderSectionsHtml(collectSections()) +
        (_emrCurrentDoc.status === 'signed' ? '<div style="text-align:right;margin-top:30px">医师签名：' + Format.escape(_emrCurrentDoc.signer_name || '') + '　' + Format.datetime(_emrCurrentDoc.signed_at) + '</div>' : '') +
        '</body></html>';
    PrintHelper.printDocument(printHtml);
}

function emr_saveAsTemplate() {
    if (!_emrEditor) { Dom.toast('仅字处理文书可存为模板', 'error'); return; }
    const cat = _emrCategories.find(c => c.id == _emrCurrentDoc.category_id);
    const content = '<div class="form-group"><label class="form-label">模板名称</label><input type="text" id="tpl-name" class="form-control" value="' + Format.escape(_emrCurrentDoc.title || '病历') + '模板"></div>';
    Dom.modal('存为模板', content, [
        { text: '保存', type: 'primary', onclick: function() {
            const name = document.getElementById('tpl-name').value.trim();
            if (!name) { Dom.toast('请输入模板名称', 'error'); return; }
            const doc = _emrEditor.getDoc();
            const schema = { mode: 'word', title: doc.title, page: doc.page, header: doc.header, body: doc.body };
            AjaxLoader.api('emr/template-save', {
                method: 'POST',
                data: {
                    name: name, record_type: (cat && cat.code) || 'admission',
                    content_delta: '{}', content_html: '',
                    schema_json: JSON.stringify(schema), category_id: _emrCurrentDoc.category_id
                }
            }).then(() => Dom.toast('模板已保存', 'success'));
        } },
        { text: '取消', type: 'default' }
    ]);
}

function emr_showVersions() {
    if (!_emrCurrentDoc) return;
    AjaxLoader.api('emr/version-list?doc_id=' + _emrCurrentDoc.id, { silent: true }).then(r => {
        const v = r.data || [];
        if (!v.length) { Dom.toast('暂无历史版本', 'error'); return; }
        const rows = v.map(x => '<div class="ctx-item" onclick="emr_restoreVersion(' + x.id + ')">[' + (x.action === 'sign' ? '签名' : x.action === 'restore' ? '恢复' : '保存') + '] ' + Format.datetime(x.created_at) + ' ' + Format.escape(x.created_name || '') + '</div>').join('');
        Dom.modal('版本历史', '<div class="emr-version-list">' + rows + '</div>', [{ text: '关闭', type: 'default' }]);
    });
}

function emr_restoreVersion(versionId) {
    Dom.modal('确认恢复', '<p>将覆盖当前草稿内容，确定恢复该历史版本吗？</p>', [
        { text: '恢复', type: 'danger', onclick: function() {
            AjaxLoader.api('emr/version-restore', { method: 'POST', data: { version_id: versionId } }).then(r => {
                Dom.toast('已恢复', 'success');
                emr_openDocument(r.data.id);
            });
        } },
        { text: '取消', type: 'default' }
    ]);
}

/* ================= 撤销/重做 (兼容旧结构) ================= */
function emr_snapshotSections() {
    const snap = JSON.stringify(collectSections());
    _emrSectionsHist = _emrSectionsHist.slice(0, _emrSectionsHistIdx + 1);
    _emrSectionsHist.push(snap);
    _emrSectionsHistIdx = _emrSectionsHist.length - 1;
}
function emr_undo() {
    if (_emrEditor) { _emrEditor.undo(); return; }
    if (_emrSectionsHistIdx <= 0) return;
    _emrSectionsHistIdx--;
    applySectionsSnapshot(_emrSectionsHist[_emrSectionsHistIdx]);
}
function emr_redo() {
    if (_emrEditor) { _emrEditor.redo(); return; }
    if (_emrSectionsHistIdx >= _emrSectionsHist.length - 1) return;
    _emrSectionsHistIdx++;
    applySectionsSnapshot(_emrSectionsHist[_emrSectionsHistIdx]);
}
function applySectionsSnapshot(snap) {
    _emrCurrentDoc.sections = JSON.parse(snap);
    document.querySelectorAll('#emr-doc-sections .emr-section-input').forEach(input => {
        const s = _emrCurrentDoc.sections.find(x => x.key === input.dataset.key);
        if (s) input.value = s.content || '';
    });
}

/* ================= 右键菜单 / 删除 ================= */
function emr_showContextMenu(e, doc) {
    let menu = '<div class="ctx-item" onclick="emr_openDocument(' + doc.id + ')">打开</div>';
    if (doc.status !== 'signed') {
        menu += '<div class="ctx-item danger" onclick="emr_deleteDoc(' + doc.id + ')">删除</div>';
    }
    const el = document.createElement('div');
    el.className = 'emr-context-menu';
    el.style.cssText = 'position:fixed;left:' + e.clientX + 'px;top:' + e.clientY + 'px;z-index:2000';
    el.innerHTML = menu;
    document.body.appendChild(el);
    const close = () => { el.remove(); document.removeEventListener('click', close); };
    setTimeout(() => document.addEventListener('click', close), 0);
}

function emr_deleteDoc(docId) {
    Dom.modal('确认删除', '<p>确认删除此文书吗？已签名文书不可删除。</p>', [
        { text: '删除', type: 'danger', onclick: function() {
            AjaxLoader.api('emr/document-delete?id=' + docId, { method: 'POST' }).then(() => {
                Dom.toast('已删除', 'success');
                document.getElementById('emr-editor').style.display = 'none';
                document.getElementById('emr-editor-empty').style.display = 'flex';
                if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }
                loadDocuments().then(renderCategoryTree);
            });
        } },
        { text: '取消', type: 'default' }
    ]);
}

/* ================= 工具条事件 ================= */
function bindToolbar() {
    const bind = (id, fn) => { const el = document.getElementById(id); if (el) el.addEventListener('click', fn); };
    bind('btn-bold', () => _emrEditor && _emrEditor.toggle('b', true));
    bind('btn-italic', () => _emrEditor && _emrEditor.toggle('i', true));
    bind('btn-underline', () => _emrEditor && _emrEditor.toggle('u', true));
    bind('btn-strike', () => _emrEditor && _emrEditor.toggle('strike', true));
    bind('btn-align-l', () => _emrEditor && _emrEditor.setAlign('left'));
    bind('btn-align-c', () => _emrEditor && _emrEditor.setAlign('center'));
    bind('btn-align-r', () => _emrEditor && _emrEditor.setAlign('right'));
    bind('btn-align-j', () => _emrEditor && _emrEditor.setAlign('justify'));
    bind('btn-undo', emr_undo);
    bind('btn-redo', emr_redo);
    bind('btn-save', emr_saveDraft);
    bind('btn-print', emr_printDoc);
    bind('btn-template', emr_saveAsTemplate);
    bind('btn-versions', emr_showVersions);
    bind('btn-sign', emr_signDoc);
    document.getElementById('emr-doc-title').addEventListener('input', () => {
        if (_emrEditor) _emrEditor.getDoc().title = document.getElementById('emr-doc-title').value;
    });
    document.getElementById('emr-doc-sections').addEventListener('input', () => {
        if (_emrCurrentDoc && _emrCurrentDoc.status !== 'signed') emr_snapshotSections();
    });
}

bindToolbar();

window.addEventListener('beforeunload', function(e) {
    if (_emrEditor && _emrEditor.isDirty()) { e.preventDefault(); e.returnValue = ''; }
});
</script>
