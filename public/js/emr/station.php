<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree">
        <div class="emr-tree-header">病历文书</div>
        <div id="emr-category-tree" class="emr-category-tree"></div>
    </div>
    <!-- 二级主视图: 结构化病历编辑区 -->
    <div class="emr-editor-pane">
        <div id="emr-editor-empty" class="emr-editor-empty">
            <p>请从左侧选择病历文书，或点击分类后的 <b>＋</b> 从模板新建</p>
        </div>
        <div id="emr-editor" class="emr-editor" style="display:none">
            <div class="emr-toolbar">
                <button type="button" class="btn btn-small" id="btn-edit-toggle">编辑模式</button>
                <button type="button" class="btn btn-small btn-success" id="btn-save">保存草稿</button>
                <button type="button" class="btn btn-small btn-primary" id="btn-sign">医师签名</button>
                <button type="button" class="btn btn-small" id="btn-undo">撤销</button>
                <button type="button" class="btn btn-small" id="btn-redo">重做</button>
                <button type="button" class="btn btn-small" id="btn-print">打印/导出PDF</button>
                <span id="emr-doc-status" class="emr-doc-status"></span>
            </div>
            <div class="emr-doc-header">
                <input type="text" id="emr-doc-title" class="emr-doc-title-input" placeholder="文书标题">
            </div>
            <div id="emr-doc-sections" class="emr-doc-sections"></div>
            <div id="emr-signature-footer" class="emr-signature-footer" style="display:none"></div>
        </div>
    </div>
</div>

<script>
var _emrCategories = [];
var _emrDocs = [];
var _emrCurrentDoc = null;
var _emrCurrentSchema = null;
var _emrHistory = [];
var _emrHistoryIdx = -1;
var _emrEditMode = true;
var _emrPatient = null;

/**
 * 病历工作站初始化
 */
async function emr_station_init() {
    // 使用全局患者上下文
    _emrPatient = PatientContext.current();
    if (!_emrPatient) {
        Dom.toast('请先选择患者', 'error');
        return;
    }
    await loadCategories();
    await loadDocuments();
    renderCategoryTree();
}

/**
 * 加载病历分类
 */
async function loadCategories() {
    const r = await AjaxLoader.api('emr/category-list', { silent: true });
    _emrCategories = r.data || [];
}

/**
 * 加载当前患者全部文书
 */
async function loadDocuments() {
    const r = await AjaxLoader.api('emr/document-list?patient_id=' + _emrPatient.id, { silent: true });
    _emrDocs = r.data || [];
}

/**
 * 渲染分类树 (一级分类 + 分类下文书 + 加号)
 */
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
            item.oncontextmenu = (e) => {
                e.preventDefault();
                emr_showContextMenu(e, d);
            };
            docList.appendChild(item);
        });
        group.appendChild(docList);
        container.appendChild(group);
    });
}

/**
 * 打开模板选择器 (按分类过滤)
 */
async function emr_openTemplatePicker(categoryId) {
    const r = await AjaxLoader.api('emr/template-list?category_id=' + categoryId + '&structured=1', { silent: true });
    const templates = r.data || [];
    if (templates.length === 0) {
        Dom.toast('该分类下暂无可用模板', 'error');
        return;
    }
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

/**
 * 从模板新建草稿文书
 */
async function emr_createFromTemplate(templateId, categoryId) {
    const tr = await AjaxLoader.api('emr/template-get?id=' + templateId, { silent: true });
    const template = tr.data;
    if (!template || !template.schema) { Dom.toast('模板数据异常', 'error'); return; }

    // 初始化 sections: 依据 schema 生成空内容
    const schema = template.schema;
    const sections = (schema.sections || []).map(s => ({
        key: s.key,
        title: s.title,
        type: s.type || 'textarea',
        required: !!s.required,
        slot: s.slot || '',
        content: ''
    }));

    const docMeta = { type: schema.meta ? schema.meta.category : 'admission', title: template.name };
    const r = await AjaxLoader.api('emr/document-save', {
        method: 'POST',
        data: {
            patient_id: _emrPatient.id,
            category_id: categoryId,
            template_id: templateId,
            title: template.name,
            sections: sections,
            doc_meta: docMeta
        }
    });
    Dom.toast('已新建草稿文书', 'success');
    await loadDocuments();
    renderCategoryTree();
    emr_openDocument(r.data.id);
}

/**
 * 打开文书到编辑区
 */
async function emr_openDocument(docId) {
    const r = await AjaxLoader.api('emr/document-get?id=' + docId, { silent: true });
    _emrCurrentDoc = r.data;

    // 从模板加载 schema 布局
    if (_emrCurrentDoc.template_id) {
        const tr = await AjaxLoader.api('emr/template-get?id=' + _emrCurrentDoc.template_id, { silent: true });
        _emrCurrentSchema = tr.data && tr.data.schema ? tr.data.schema : null;
    }
    _emrEditMode = _emrCurrentDoc.status !== 'signed';
    _emrHistory = [];
    _emrHistoryIdx = -1;

    document.getElementById('emr-editor-empty').style.display = 'none';
    document.getElementById('emr-editor').style.display = 'block';

    renderEditor();
}

/**
 * 渲染结构化编辑器
 */
function renderEditor() {
    const doc = _emrCurrentDoc;
    const schema = _emrCurrentSchema;
    const isSigned = doc.status === 'signed';

    document.getElementById('emr-doc-title').value = doc.title || '';
    document.getElementById('btn-edit-toggle').textContent = isSigned ? '已签名(只读)' : (_emrEditMode ? '编辑模式' : '预览模式');
    document.getElementById('btn-edit-toggle').disabled = isSigned;
    document.getElementById('btn-save').disabled = isSigned;
    document.getElementById('btn-sign').disabled = isSigned || !_emrEditMode;
    document.getElementById('btn-undo').disabled = isSigned;
    document.getElementById('btn-redo').disabled = isSigned;

    const statusEl = document.getElementById('emr-doc-status');
    statusEl.textContent = isSigned ? '已签名 · 只读锁定' : '草稿 · 可编辑';
    statusEl.className = 'emr-doc-status ' + (isSigned ? 'signed' : 'draft');

    // 渲染段落
    const container = document.getElementById('emr-doc-sections');
    container.innerHTML = '';
    const sections = doc.sections || [];

    if (sections.length === 0) {
        container.innerHTML = '<div class="emr-section-empty">该文书无结构化段落</div>';
    }

    sections.forEach(s => {
        const sec = document.createElement('div');
        sec.className = 'emr-section' + (s.required ? ' required' : '');
        const editable = !isSigned && _emrEditMode;

        let bodyHtml = '';
        if (s.type === 'textarea') {
            if (editable) {
                bodyHtml = '<textarea class="emr-section-input" data-key="' + Format.escape(s.key) + '" rows="4" placeholder="' + Format.escape(s.slot || '') + '">' + Format.escape(s.content || '') + '</textarea>';
            } else {
                bodyHtml = '<div class="emr-section-render">' + Format.escape(s.content || '') + '</div>';
            }
        } else {
            if (editable) {
                bodyHtml = '<input type="text" class="emr-section-input" data-key="' + Format.escape(s.key) + '" value="' + Format.escape(s.content || '') + '">';
            } else {
                bodyHtml = '<div class="emr-section-render">' + Format.escape(s.content || '') + '</div>';
            }
        }

        sec.innerHTML = '<div class="emr-section-title">' + Format.escape(s.title) + (s.required ? ' <span class="required-mark">*</span>' : '') + '</div>' + bodyHtml;
        container.appendChild(sec);
    });

    // 签名栏
    const sigFooter = document.getElementById('emr-signature-footer');
    if (isSigned) {
        const signer = doc.signer_name || '医师';
        sigFooter.style.display = 'block';
        sigFooter.innerHTML = '医师签名：' + Format.escape(signer) + '　' + Format.datetime(doc.signed_at);
    } else {
        sigFooter.style.display = 'none';
        sigFooter.innerHTML = '';
    }
}

/**
 * 收集当前编辑器中的段落值
 */
function collectSections() {
    const doc = _emrCurrentDoc;
    const sections = JSON.parse(JSON.stringify(doc.sections || []));
    document.querySelectorAll('.emr-section-input').forEach(input => {
        const key = input.dataset.key;
        const s = sections.find(x => x.key === key);
        if (s) s.content = input.value;
    });
    return sections;
}

/**
 * 保存草稿
 */
async function emr_saveDraft() {
    if (!_emrCurrentDoc || _emrCurrentDoc.status === 'signed') return;
    _emrCurrentDoc.sections = collectSections();
    _emrCurrentDoc.title = document.getElementById('emr-doc-title').value;
    await AjaxLoader.api('emr/document-save', {
        method: 'POST',
        data: {
            id: _emrCurrentDoc.id,
            patient_id: _emrPatient.id,
            category_id: _emrCurrentDoc.category_id,
            template_id: _emrCurrentDoc.template_id,
            title: _emrCurrentDoc.title,
            sections: _emrCurrentDoc.sections,
            doc_meta: _emrCurrentDoc.doc_meta,
            content_html: renderSectionsHtml(_emrCurrentDoc.sections)
        }
    });
    Dom.toast('草稿已保存', 'success');
}

/**
 * 将段落渲染为打印用 HTML
 */
function renderSectionsHtml(sections) {
    let html = '<div class="emr-print-doc">';
    sections.forEach(s => {
        html += '<div class="emr-print-section"><div class="emr-print-title">' + Format.escape(s.title) + '</div><div class="emr-print-content">' + Format.escape(s.content || '') + '</div></div>';
    });
    html += '</div>';
    return html;
}

/**
 * 医师签名 (校验身份)
 */
function emr_signDoc() {
    if (!_emrCurrentDoc || _emrCurrentDoc.status === 'signed') return;
    const content = '<div class="form-group"><label class="form-label">请输入密码/工号确认身份</label><input type="password" id="sign-password" class="form-control"></div>';
    Dom.modal('医师签名', content, [
        { text: '确认签名', type: 'primary', onclick: function() {
            const pwd = document.getElementById('sign-password').value;
            if (!pwd) { Dom.toast('请输入密码', 'error'); return; }
            AjaxLoader.api('emr/document-sign', {
                method: 'POST',
                data: { id: _emrCurrentDoc.id, password: pwd }
            }).then(r => {
                Dom.toast('签名成功', 'success');
                emr_openDocument(_emrCurrentDoc.id);
                loadDocuments().then(renderCategoryTree);
            });
        } },
        { text: '取消', type: 'default' }
    ]);
}

/**
 * 撤销 / 重做 (段落快照栈)
 */
function emr_snapshot() {
    if (!_emrCurrentDoc) return;
    const snap = JSON.stringify(collectSections());
    _emrHistory = _emrHistory.slice(0, _emrHistoryIdx + 1);
    _emrHistory.push(snap);
    _emrHistoryIdx = _emrHistory.length - 1;
}

function emr_undo() {
    if (_emrHistoryIdx <= 0) return;
    _emrHistoryIdx--;
    applySnapshot(_emrHistory[_emrHistoryIdx]);
}

function emr_redo() {
    if (_emrHistoryIdx >= _emrHistory.length - 1) return;
    _emrHistoryIdx++;
    applySnapshot(_emrHistory[_emrHistoryIdx]);
}

function applySnapshot(snap) {
    const sections = JSON.parse(snap);
    _emrCurrentDoc.sections = sections;
    document.querySelectorAll('.emr-section-input').forEach(input => {
        const s = sections.find(x => x.key === input.dataset.key);
        if (s) input.value = s.content || '';
    });
}

/**
 * 右键上下文菜单
 */
function emr_showContextMenu(e, doc) {
    let menu = '';
    menu += '<div class="ctx-item" onclick="emr_openDocument(' + doc.id + ')">打开</div>';
    if (doc.status !== 'signed') {
        menu += '<div class="ctx-item" onclick="emr_openDocument(' + doc.id + ')">编辑</div>';
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

/**
 * 删除草稿文书
 */
function emr_deleteDoc(docId) {
    Dom.modal('确认删除', '<p>确认删除此文书吗？已签名文书不可删除。</p>', [
        { text: '删除', type: 'danger', onclick: function() {
            AjaxLoader.api('emr/document-delete?id=' + docId, { method: 'POST' }).then(() => {
                Dom.toast('已删除', 'success');
                document.getElementById('emr-editor').style.display = 'none';
                document.getElementById('emr-editor-empty').style.display = 'block';
                loadDocuments().then(renderCategoryTree);
            });
        } },
        { text: '取消', type: 'default' }
    ]);
}

/**
 * 打印 / 导出 PDF
 */
function emr_printDoc() {
    if (!_emrCurrentDoc) return;
    const printHtml = '<html><head><title>' + Format.escape(_emrCurrentDoc.title || '病历') + '</title>' +
        '<style>.emr-print-doc{font-family:仿宋_GB2312,SimSun,serif;font-size:14px;line-height:1.5;padding:20px}.emr-print-section{margin-bottom:12px}.emr-print-title{font-weight:bold;margin-bottom:4px}.emr-print-content{text-indent:2em}</style>' +
        '</head><body>' + renderSectionsHtml(collectSections()) +
        ( _emrCurrentDoc.status === 'signed' ? '<div style="text-align:right;margin-top:30px">医师签名：' + Format.escape(_emrCurrentDoc.signer_name || '') + '　' + Format.datetime(_emrCurrentDoc.signed_at) + '</div>' : '') +
        '</body></html>';
    const win = window.open('', '_blank');
    win.document.write(printHtml);
    win.document.close();
    win.print();
}

// 工具条事件绑定
document.getElementById('btn-save').addEventListener('click', emr_saveDraft);
document.getElementById('btn-sign').addEventListener('click', emr_signDoc);
document.getElementById('btn-undo').addEventListener('click', emr_undo);
document.getElementById('btn-redo').addEventListener('click', emr_redo);
document.getElementById('btn-print').addEventListener('click', emr_printDoc);
document.getElementById('btn-edit-toggle').addEventListener('click', function() {
    if (_emrCurrentDoc.status === 'signed') return;
    _emrEditMode = !_emrEditMode;
    renderEditor();
});
document.getElementById('emr-doc-sections').addEventListener('input', function() {
    if (_emrCurrentDoc && _emrCurrentDoc.status !== 'signed') emr_snapshot();
});
</script>