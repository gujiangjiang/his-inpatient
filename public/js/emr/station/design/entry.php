<script>
async function emr_enterTemplateDesign(tid, opts) {
    opts = opts || {};
    let tpl = null, schema = null;
    if (tid) {
        const tr = await AjaxLoader.api('emr/template-get?id=' + tid, { silent: true });
        tpl = tr.data;
        if (tpl) schema = parseSchema(tpl.schema_json);
    }
    if (!schema || schema.mode !== 'word') { Dom.toast('该模板不支持设计（仅字处理模板）', 'error'); return; }
    _emrDesign = {
        id: tid || null,
        name: tpl ? tpl.name : (opts.name || '新模板'),
        categoryId: tpl ? tpl.category_id : (opts.categoryId || null),
        recordType: tpl ? tpl.record_type : (opts.recordType || 'admission')
    };
    if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }
    document.getElementById('emr-editor-empty').style.display = 'none';
    document.getElementById('emr-editor').style.display = 'flex';
    emr_fitEditorZoom(); /* 窄屏: 编辑器可见后重算画布缩放 */
    /* 设计模式保留格式工具条 (加粗/对齐等), 仅隐藏文档操作按钮 */
    document.getElementById('emr-toolbar').style.display = '';
    document.getElementById('emr-editor').classList.add('emr-design-on');
    document.getElementById('emr-design-bar').style.display = 'flex';
    document.getElementById('emr-design-name').textContent = '模板设计：' + _emrDesign.name;
    /* 模板设计禁用「引用」: 收起引用面板并隐藏入口按钮 (避免误操作) */
    document.getElementById('emr-ref-panel').style.display = 'none';
    document.getElementById('btn-ref-panel').style.display = 'none';
    const dsEl = document.getElementById('emr-doc-status');
    dsEl.textContent = ''; dsEl.className = 'emr-doc-status';
    /* 左栏切换为「模板设计」面板 */
    document.getElementById('emr-tree-title').textContent = '模板设计';
    document.getElementById('emr-category-tree').style.display = 'none';
    document.getElementById('emr-tree-foot').style.display = 'none';
    document.getElementById('emr-design-panel').style.display = '';
    applyPageCss(schema.page);
    EmrOps.setDesignMode(true);
    _emrEditor = EmrEditor.create({
        container: document.getElementById('emr-editor-canvas'),
        doc: EmrDoc.fromSchema(schema),
        guard: null,
        patientInfo: {}, /* 设计模板与患者无关: 页眉/引用以占位符呈现 (render 层 design 标志) */
        design: true,
        onItemContext: emr_dsnItemContext, /* 右击元素: 属性/删除 菜单 */
        onTableContext: emr_dsnTableContext, /* 右击表格: 表格属性/删除 */
        onChange: null,
        onStatus: updateEditorStatus
    });
}

function emr_exitTemplateDesign() {
    EmrOps.setDesignMode(false);
    _emrDesign = null;
    /* 左栏还原为病历文书树 */
    document.getElementById('emr-tree-title').textContent = '病历文书';
    document.getElementById('emr-category-tree').style.display = '';
    document.getElementById('emr-tree-foot').style.display = '';
    document.getElementById('emr-design-panel').style.display = 'none';
    document.getElementById('emr-editor').classList.remove('emr-design-on');
    if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }
    document.getElementById('emr-design-bar').style.display = 'none';
    document.getElementById('emr-toolbar').style.display = '';
    document.getElementById('btn-ref-panel').style.display = ''; /* 恢复引用入口 */
    document.getElementById('emr-editor').style.display = 'none';
    document.getElementById('emr-editor-empty').style.display = 'flex';
}

async function emr_saveTemplateDesign() {
    if (!_emrDesign || !_emrEditor) return;
    const doc = _emrEditor.getDoc();
    /* 自动计算项预检: 公式涉及的字段必须在模板中存在 (否则该模板无法用于新建文书) */
    const missCalc = (window.EmrCalc && EmrCalc.missing(doc.body)) || [];
    if (missCalc.length) {
        Dom.toast('自动计算项缺少字段: ' + missCalc.map(function (m) { return m.name + '（' + m.keys.join('、') + '）'; }).join('；'), 'error');
        return;
    }
    /* 文书标题 = 抬头 doc_name (与模板名称分离, 属性弹窗可改); schema.title 与之一致 */
    const docTitle = (doc.header && doc.header.doc_name) || _emrDesign.name;
    doc.title = docTitle;
    /* mv = 模型版本 (值标记/混排): 旧 schema 在载入设计时已迁移, 保存即升级 */
    const schema = { mode: 'word', mv: EmrDoc.MODEL_V, title: docTitle, page: doc.page, header: doc.header, body: doc.body };
    const payload = {
        name: _emrDesign.name,
        record_type: _emrDesign.recordType,
        content_delta: '{}', content_html: '',
        schema_json: JSON.stringify(schema),
        category_id: _emrDesign.categoryId
    };
    if (_emrDesign.id) payload.id = _emrDesign.id;
    const r = await AjaxLoader.api('emr/template-save', { method: 'POST', data: payload });
    if (!_emrDesign.id) _emrDesign.id = r.data.id;
    _emrEditor.markSaved();
    Dom.toast('模板已保存', 'success');
}

/* 可插入字段目录: 患者信息(dyn 自动填入) + 病历字段(ref 引用其他文书值) */
var EMR_FIELD_CATALOG = [
    { g: '患者信息（自动填入）', items: [
        ['dyn', 'p_name', '姓名'], ['dyn', 'p_gender', '性别'], ['dyn', 'p_age', '年龄']
    ] },
    { g: '病历字段（引用其他文书）', items: [
        ['ref', 'chief_complaint_val', '主诉'], ['ref', 'present_illness_val', '现病史'],
        ['ref', 'past_history_val', '既往史'], ['ref', 'personal_history_val', '个人史'],
        ['ref', 'marital_history_val', '婚育史'], ['ref', 'menstrual_history_val', '月经史'],
        ['ref', 'family_history_val', '家族史'], ['ref', 'exam_general_val', '体格检查·一般情况'],
        ['ref', 'special_exam_val', '专科检查'], ['ref', 'aux_exam_val', '辅助检查'],
        ['ref', 'admission_condition_val', '入院情况'], ['ref', 'admission_diag_val', '入院诊断'],
        ['ref', 'initial_diagnosis_value', '初步诊断'], ['ref', 'course_val', '诊疗经过'],
        ['ref', 'discharge_condition_val', '出院情况'], ['ref', 'discharge_diag_val', '出院诊断'],
        ['ref', 'discharge_orders_val', '出院医嘱'], ['ref', 'vital_bmi_value', 'BMI']
    ] }
];
</script>
