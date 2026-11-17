<script>
/* ================= 模板导入 / 导出 (设计工具条右上角) =================
 * 导出: 当前设计模板 → 文件 <名称>.emr-tpl.json { app, kind:'word', mv, name, doc_name, record_type, category_id, schema }
 * 导入: 选择导出文件 → 解析校验 → 弹窗确认名称/文书标题/分类 → 新建模板并进入设计 (永不覆盖现有模板) */

/** 导出当前设计模板为 JSON 文件 */
function emr_tplExport() {
    if (!_emrDesign || !_emrEditor) { Dom.toast('请先进入模板设计', 'error'); return; }
    const doc = _emrEditor.getDoc();
    const docTitle = (doc.header && doc.header.doc_name) || _emrDesign.name;
    const payload = {
        app: 'his-emr-template',
        kind: 'word',
        mv: EmrDoc.MODEL_V,
        name: _emrDesign.name,
        doc_name: docTitle,
        record_type: _emrDesign.recordType,
        category_id: _emrDesign.categoryId,
        exported_at: new Date().toISOString(),
        schema: { mode: 'word', mv: EmrDoc.MODEL_V, title: docTitle, page: doc.page, header: doc.header, body: doc.body }
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = _emrDesign.name.replace(/[\\/:*?"<>|]/g, '_') + '.emr-tpl.json';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 3000);
    Dom.toast('已导出模板文件', 'success');
}

/** 解析导入文件内容: 返回 {name, docName, recordType, categoryId, schema} 或 null */
function emr_tplParseImport(text) {
    let obj = null;
    try { obj = JSON.parse(text); } catch (e) { return null; }
    if (!obj || typeof obj !== 'object') return null;
    const schema = obj.schema && typeof obj.schema === 'object' ? obj.schema : (obj.mode ? obj : null);
    if (!schema) return null;
    const hasBody = schema.mode === 'word' && Array.isArray(schema.body);
    const hasSections = Array.isArray(schema.sections);
    if (!hasBody && !hasSections) return null;
    return {
        name: String(obj.name || schema.title || '导入模板'),
        docName: String(obj.doc_name || (schema.header && schema.header.doc_name) || obj.name || schema.title || '导入模板'),
        recordType: String(obj.record_type || 'admission'),
        categoryId: obj.category_id != null ? obj.category_id : null,
        schema: schema
    };
}

/** 导入确认弹窗: 名称/文书标题/分类可改, 确认后新建模板并进入设计 */
async function emr_tplImportDialog(parsed) {
    let cats = [];
    try { cats = ((await AjaxLoader.api('emr/category-list', { silent: true })).data) || []; } catch (e) { cats = []; }
    const catOpts = cats.map(function (c) {
        return '<option value="' + c.id + '"' + (String(c.id) === String(parsed.categoryId) ? ' selected' : '') + '>' + Format.escape(c.name) + '</option>';
    }).join('');
    const content =
        '<p class="emr-tpl-hint">导入将新建一个模板（不会覆盖现有模板）；标题与所属分类可调整。</p>'
        + '<div class="form-group"><label class="form-label">模板名称</label><input id="tpi-name" class="form-control" value="' + Format.escape(parsed.name) + '"></div>'
        + '<div class="form-group"><label class="form-label">文书标题（病历抬头）</label><input id="tpi-title" class="form-control" value="' + Format.escape(parsed.docName) + '"></div>'
        + '<div class="form-group"><label class="form-label">所属分类</label><select id="tpi-cat" class="form-control">' + catOpts + '</select></div>';
    const sub = Dom.modal('导入模板', content, [
        { text: '导入并设计', type: 'primary', onclick: async function () {
            const name = (sub.querySelector('#tpi-name').value || '').trim();
            const docTitle = (sub.querySelector('#tpi-title').value || '').trim() || name;
            if (!name) { Dom.toast('请输入模板名称', 'error'); return false; }
            const catId = sub.querySelector('#tpi-cat').value || null;
            const schema = Object.assign({}, parsed.schema);
            schema.title = docTitle;
            schema.header = Object.assign({}, schema.header || {}, { doc_name: docTitle });
            const resp = await AjaxLoader.api('emr/template-save', { method: 'POST', data: {
                name: name, record_type: parsed.recordType,
                content_delta: '{}', content_html: '',
                schema_json: JSON.stringify(schema),
                category_id: catId
            } });
            Dom.toast('模板已导入', 'success');
            emr_enterTemplateDesign(resp.data.id);
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#tpi-name').focus();
}

/** 选择文件 → 解析 → 确认弹窗 */
function emr_tplImport() {
    const inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = '.json,application/json';
    inp.addEventListener('change', function () {
        const file = inp.files && inp.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function () {
            const parsed = emr_tplParseImport(String(reader.result || ''));
            if (!parsed) { Dom.toast('文件格式不正确：需为本系统导出的模板 JSON', 'error'); return; }
            emr_tplImportDialog(parsed);
        };
        reader.readAsText(file, 'utf-8');
    });
    inp.click();
}
</script>
