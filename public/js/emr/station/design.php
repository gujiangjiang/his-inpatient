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
    /* 设计模式保留格式工具条 (加粗/对齐等), 仅隐藏文档操作按钮 */
    document.getElementById('emr-toolbar').style.display = '';
    document.getElementById('emr-editor').classList.add('emr-design-on');
    document.getElementById('emr-design-bar').style.display = 'flex';
    document.getElementById('emr-design-name').textContent = '模板设计：' + _emrDesign.name;
    const dsEl = document.getElementById('emr-doc-status');
    dsEl.textContent = ''; dsEl.className = 'emr-doc-status';
    /* 左栏切换为「模板设计」面板 */
    document.getElementById('emr-tree-header').textContent = '模板设计';
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
    document.getElementById('emr-tree-header').textContent = '病历文书';
    document.getElementById('emr-category-tree').style.display = '';
    document.getElementById('emr-tree-foot').style.display = '';
    document.getElementById('emr-design-panel').style.display = 'none';
    document.getElementById('emr-editor').classList.remove('emr-design-on');
    if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }
    document.getElementById('emr-design-bar').style.display = 'none';
    document.getElementById('emr-toolbar').style.display = '';
    document.getElementById('emr-editor').style.display = 'none';
    document.getElementById('emr-editor-empty').style.display = 'flex';
}

async function emr_saveTemplateDesign() {
    if (!_emrDesign || !_emrEditor) return;
    const doc = _emrEditor.getDoc();
    doc.title = _emrDesign.name;
    /* mv = 模型版本 (值标记/混排): 旧 schema 在载入设计时已迁移, 保存即升级 */
    const schema = { mode: 'word', mv: EmrDoc.MODEL_V, title: _emrDesign.name, page: doc.page, header: doc.header, body: doc.body };
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

/** 插入字段展开列表: 分组字段目录 + 搜索 + 自定义字段入口; 点击即插入光标处 */
function emr_dsnToggleFields() {
    const head = document.getElementById('dsn-sec-field');
    const box = document.getElementById('dsn-fields');
    const open = head.classList.toggle('open');
    box.style.display = open ? '' : 'none';
    if (!open) return;
    if (!box.dataset.built) { box.dataset.built = '1'; emr_dsnRenderFieldList(''); }
    const inp = box.querySelector('.emr-dsn-search');
    if (inp) inp.focus();
}
function emr_dsnRenderFieldList(q) {
    const box = document.getElementById('dsn-fields');
    const kw = (q || '').trim();
    let html = '<input class="emr-dsn-search form-control" placeholder="搜索字段…" value="' + Format.escape(kw) + '">';
    EMR_FIELD_CATALOG.forEach(function (grp) {
        const items = grp.items.filter(function (x) { return !kw || x[2].indexOf(kw) >= 0 || x[1].indexOf(kw) >= 0; });
        if (!items.length) return;
        html += '<div class="emr-dsn-group">' + Format.escape(grp.g) + '</div>';
        html += items.map(function (x) {
            return '<div class="emr-dsn-field" data-t="' + x[0] + '" data-k="' + x[1] + '">' + Format.escape(x[2]) + '</div>';
        }).join('');
    });
    html += '<div class="emr-dsn-field emr-dsn-custom" id="dsn-field-custom">＋ 自定义字段（标签＋输入槽）</div>';
    box.innerHTML = html;
    const inp = box.querySelector('.emr-dsn-search');
    inp.addEventListener('input', function () { emr_dsnRenderFieldList(inp.value); });
    box.querySelectorAll('.emr-dsn-field[data-k]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!_emrEditor) return;
            const t = el.dataset.t, k = el.dataset.k;
            if (t === 'dyn') _emrEditor.insertItem({ t: 'dyn', key: k, text: '' });
            else _emrEditor.insertRef(k, '＿＿');
        });
    });
    const cu = box.querySelector('#dsn-field-custom');
    if (cu) cu.addEventListener('click', function () { emr_dsnInsertField(false); });
}

/* 固定元素目录: 设计期插入的原子元素 (不可日常删除, 右击可改属性/删除) */
var EMR_FIXED_ELEMENTS = [
    { id: 'date', label: '日期组件', hint: '默认日期+时间, 右击可改类型与格式' },
    { id: 'text', label: '固定文字', hint: '不可删除的描述文字（如 主诉：现病史：等标签）' },
    { id: 'sign', label: '签名元素', hint: '医师签名行（签名时自动填入姓名）' }
];

/** 固定元素展开列表 */
function emr_dsnToggleFixed() {
    const head = document.getElementById('dsn-sec-fixed');
    const box = document.getElementById('dsn-fixed');
    const open = head.classList.toggle('open');
    box.style.display = open ? '' : 'none';
    if (!open || box.dataset.built) return;
    box.dataset.built = '1';
    box.innerHTML = EMR_FIXED_ELEMENTS.map(function (x) {
        return '<div class="emr-dsn-field" data-fixed="' + x.id + '" title="' + Format.escape(x.hint) + '">' + Format.escape(x.label) + '</div>';
    }).join('');
    box.querySelectorAll('.emr-dsn-field').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!_emrEditor) return;
            if (el.dataset.fixed === 'date') {
                emr_dsnInsertDate();
            } else if (el.dataset.fixed === 'text') {
                if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
                const sub = Dom.modal('插入固定文字',
                    '<div class="form-group"><label class="form-label">文字内容（书写时不可删除，如 主诉：）</label>'
                    + '<input id="dsn-fixedtext" class="form-control" placeholder="如：主诉："></div>',
                    [
                        { text: '插入', type: 'primary', onclick: function () {
                            if (!_emrEditor) return;
                            const t = (document.getElementById('dsn-fixedtext').value || '').trim();
                            if (!t) { Dom.toast('请输入文字内容', 'error'); return; }
                            _emrEditor.insertItem({ t: 'fx', key: 't' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10), text: t });
                        } },
                        { text: '取消', type: 'default' }
                    ]);
                const inp = sub.querySelector('#dsn-fixedtext');
                inp.focus();
            } else if (el.dataset.fixed === 'sign') {
                _emrEditor.insertSignLine();
            }
        });
    });
}

/** 选项文本解析: 换行/顿号/逗号/分号分隔 (创建与属性配置一致) */
function emrParseOpts(v) {
    return String(v || '').split(/[\n、，,;；]/).map(function (x) { return x.trim(); }).filter(Boolean);
}

/** 插入日期组件 (类型/格式/占位描述; 占位描述即未选择时的显示文字) */
function emr_dsnInsertDate() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const blk = emrPropDtBlock({ t: 'slot', dt: { mode: 'datetime', fmt: '' } });
    const content = blk.html
        + '<div class="form-group"><label class="form-label">占位描述（未选择时显示，打印不可见）</label>'
        + '<input id="dsn-dt-ph" class="form-control" value="请选择日期和时间" placeholder="如：请选择日期和时间（可留空）"></div>';
    const sub = Dom.modal('插入日期组件', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const dt = blk.read(sub);
            const ph = (sub.querySelector('#dsn-dt-ph').value || '').trim();
            _emrEditor.insertItem({
                t: 'slot',
                key: 'd' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10),
                ph: ph,
                dt: dt
            });
        } },
        { text: '取消', type: 'default' }
    ]);
    blk.bind(sub);
}

/** 表格快速选择器: 6×6 移入高亮 n×m, 点击插入 */
function emr_dsnBuildTablePicker() {
    const box = document.getElementById('emr-tblpick');
    if (box.dataset.built) return;
    box.dataset.built = '1';
    let cells = '';
    for (let r = 1; r <= 6; r++) for (let c = 1; c <= 6; c++) {
        cells += '<span data-r="' + r + '" data-c="' + c + '"></span>';
    }
    box.innerHTML = cells;
    const label = document.getElementById('emr-tblpick-label');
    const all = Array.from(box.querySelectorAll('span'));
    const clear = function () { all.forEach(function (x) { x.classList.remove('on'); }); };
    all.forEach(function (x) {
        x.addEventListener('mouseover', function () {
            const R = +x.dataset.r, C = +x.dataset.c;
            all.forEach(function (y) { y.classList.toggle('on', +y.dataset.r <= R && +y.dataset.c <= C); });
            label.textContent = R + ' 行 × ' + C + ' 列';
        });
        x.addEventListener('click', function () {
            if (!_emrEditor) return;
            _emrEditor.insertTable(+x.dataset.r, +x.dataset.c);
            label.textContent = '已插入 ' + x.dataset.r + ' 行 × ' + x.dataset.c + ' 列（拖动框线可调列宽/行高）';
        });
    });
    box.addEventListener('mouseleave', function () { clear(); if (label.textContent.indexOf('已插入') < 0) label.textContent = '移入选择行列（拖动框线可调列宽/行高）'; });
}

/** 设计模式右击表格: 浮动菜单 (添加行/列二级菜单、删除行/列二级菜单、表格属性、删除表格) */
function emr_dsnTableContext(tblId, ri, ci, x, y) {
    const items = [
        { a: 'add', label: '添加', sub: [
            { a: 'add-row-up', label: '向上添加行' }, { a: 'add-row-down', label: '向下添加行' },
            { a: 'add-col-left', label: '向左添加列' }, { a: 'add-col-right', label: '向右添加列' }
        ] },
        { a: 'delmenu', label: '删除', sub: [
            { a: 'del-row', label: '删除当前行', danger: true }, { a: 'del-col', label: '删除当前列', danger: true }
        ] },
        { a: 'props', label: '表格属性…' },
        { a: 'tbl-del', label: '删除表格', danger: true }
    ];
    EmrUI.floatMenu(items, x, y, function (a) {
        const withTbl = function (fn) {
            if (!_emrEditor) return;
            const tbl = EmrOps.findBlockById(_emrEditor.getDoc().body, tblId);
            if (!tbl || tbl.t !== 'tbl') { Dom.toast('表格不存在', 'error'); return; }
            fn(tbl);
            _emrEditor.refresh();
        };
        if (a === 'add-row-up') return withTbl(function (t) { EmrOps.tableInsertRow(t, ri, true); });
        if (a === 'add-row-down') return withTbl(function (t) { EmrOps.tableInsertRow(t, ri, false); });
        if (a === 'add-col-left') return withTbl(function (t) { EmrOps.tableInsertCol(t, ci, true); });
        if (a === 'add-col-right') return withTbl(function (t) { EmrOps.tableInsertCol(t, ci, false); });
        if (a === 'del-row') return withTbl(function (t) { EmrOps.tableDelRowAt(t, ri); });
        if (a === 'del-col') return withTbl(function (t) { EmrOps.tableDelColAt(t, ci); });
        if (a === 'props') { emr_dsnTableProps(tblId); return; }
        if (a === 'tbl-del') {
            Dom.modal('删除表格', '<p>确认从模板中删除整个表格？</p>', [
                { text: '删除', type: 'danger', onclick: function () {
                    if (!_emrEditor) return;
                    EmrOps.removeBlockById(_emrEditor.getDoc().body, tblId);
                    _emrEditor.refresh();
                } },
                { text: '取消', type: 'default' }
            ]);
        }
    });
}

/** 表格属性: 框线样式(默认/加粗/无) + 行数列数增减 + 删除表格 */
function emr_dsnTableProps(tblId) {
    const tbl = EmrOps.findBlockById(_emrEditor.getDoc().body, tblId);
    if (!tbl || tbl.t !== 'tbl') { Dom.toast('表格不存在', 'error'); return; }
    const curBd = (tbl.tblPr && tbl.tblPr.bd === 0) ? 'none' : (tbl.tblPr && tbl.tblPr.bd === 2) ? 'bold' : 'default';
    const bdOpts = [['default', '默认细线'], ['bold', '加粗框线'], ['none', '无框线']].map(function (m) {
        return '<label class="emr-prop-radio"><input type="radio" name="tbl-bd" value="' + m[0] + '"' + (curBd === m[0] ? ' checked' : '') + '> ' + m[1] + '</label>';
    }).join('');
    const sub = Dom.modal('表格属性',
        '<div class="form-group"><label class="form-label">框线样式</label><div class="emr-prop-radios">' + bdOpts + '</div></div>'
        + '<div class="form-group emr-tblp-rows"><label class="form-label">行数：<b id="tblp-rows">' + tbl.rows.length + '</b></label>'
        + '<button type="button" class="btn btn-small" id="tblp-addrow">添加行</button>'
        + '<button type="button" class="btn btn-small" id="tblp-delrow">删除行</button></div>'
        + '<div class="form-group emr-tblp-rows"><label class="form-label">列数：<b id="tblp-cols">' + (tbl.grid || []).length + '</b></label>'
        + '<button type="button" class="btn btn-small" id="tblp-addcol">添加列</button>'
        + '<button type="button" class="btn btn-small" id="tblp-delcol">删除列</button></div>',
        [
            { text: '确定', type: 'primary', onclick: function () {
                const sel = sub.querySelector('input[name="tbl-bd"]:checked');
                const v = sel ? sel.value : 'default';
                tbl.tblPr = tbl.tblPr || {};
                if (v === 'none') tbl.tblPr.bd = 0;
                else if (v === 'bold') tbl.tblPr.bd = 2;
                else delete tbl.tblPr.bd;
                if (!Object.keys(tbl.tblPr).length) delete tbl.tblPr;
                _emrEditor.refresh();
                Dom.toast('表格属性已更新', 'success');
            } },
            { text: '删除表格', type: 'danger', onclick: function () {
                EmrOps.removeBlockById(_emrEditor.getDoc().body, tblId);
                _emrEditor.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    const upd = function () {
        sub.querySelector('#tblp-rows').textContent = tbl.rows.length;
        sub.querySelector('#tblp-cols').textContent = (tbl.grid || []).length;
        _emrEditor.refresh();
    };
    sub.querySelector('#tblp-addrow').addEventListener('click', function () { EmrOps.tableAddRow(tbl); upd(); });
    sub.querySelector('#tblp-delrow').addEventListener('click', function () { EmrOps.tableDelRow(tbl); upd(); });
    sub.querySelector('#tblp-addcol').addEventListener('click', function () { EmrOps.tableAddCol(tbl); upd(); });
    sub.querySelector('#tblp-delcol').addEventListener('click', function () { EmrOps.tableDelCol(tbl); upd(); });
}

/** 设计模式右击元素: 浮动菜单 (属性 / 删除固定元素) */
function emr_dsnItemContext(item, p, o, x, y) {
    const items = [{ a: 'props', label: '元素属性…' }];
    if (EmrDoc.isProtected(item)) items.push({ a: 'del', label: '删除该固定元素', danger: true });
    EmrUI.floatMenu(items, x, y, function (a) {
        if (a === 'props') { emr_dsnShowProps(item, p, o); return; }
        if (a === 'del') {
            Dom.modal('删除固定元素', '<p>确认从模板中删除该元素？删除后文书结构中不再保留。</p>', [
                { text: '删除', type: 'danger', onclick: function () { if (_emrEditor) _emrEditor.removeItem(p, item); } },
                { text: '取消', type: 'default' }
            ]);
        }
    });
}

/** 动态项用途说明 */
function emr_dynHint(key) {
    const D = {
        vital_bmi_value: 'BMI 自动计算', sign_names: '医师签名（签名时自动填入）',
        initial_diagnosis_value: '初步诊断（点击同步诊断）', diag_admission: '入院诊断（打开时自动填入）',
        diag_discharge: '出院诊断（打开时自动填入）', p_name: '患者姓名', p_gender: '患者性别', p_age: '患者年龄'
    };
    return D[key] || key;
}

/** 元素名称推断 (未设置自定义名时) */
function emr_dsnItemName(item) {
    if (item.name) return item.name;
    if (item.t === 'fx') return item.text || '';
    return item.key || item.t;
}

/** 属性对话框·日期块 (slot.dt): 返回 {html, bind(sub), read(sub)} */
function emrPropDtBlock(item) {
    const mode = item.dt.mode || 'datetime';
    const fmt = item.dt.fmt || '';
    const presets = ['', 'yyyy-mm-dd', 'yyyy年mm月dd日', 'yyyy-mm-dd hh:nn', 'yyyy年mm月dd日 hh时nn分', 'hh:nn'];
    const html = '<div class="form-group"><label class="form-label">日期类型</label><div class="emr-prop-radios">'
        + [['datetime', '日期+时间'], ['date', '仅日期'], ['time', '仅时间']].map(function (m) {
            return '<label class="emr-prop-radio"><input type="radio" name="dt-mode" value="' + m[0] + '"' + (mode === m[0] ? ' checked' : '') + '> ' + m[1] + '</label>';
        }).join('') + '</div></div>'
        + '<div class="form-group"><label class="form-label">日期格式</label>'
        + '<select id="prop-dt-fmt" class="form-control">' + presets.map(function (x) {
            return '<option value="' + x + '"' + (fmt === x ? ' selected' : '') + '>' + (x || '默认(按类型)') + '</option>';
        }).join('') + '</select>'
        + '<input id="prop-dt-fmtc" class="form-control" style="margin-top:6px" placeholder="自定义格式（可留空）: yyyy/mm/dd/hh/nn" value="' + (presets.indexOf(fmt) < 0 ? Format.escape(fmt) : '') + '"></div>';
    return {
        html: html,
        bind: function () {},
        read: function (sub) {
            const modeSel = sub.querySelector('input[name="dt-mode"]:checked');
            const fmtSel = (sub.querySelector('#prop-dt-fmt').value || '').trim();
            const fmtCustom = (sub.querySelector('#prop-dt-fmtc').value || '').trim();
            return { mode: modeSel ? modeSel.value : 'datetime', fmt: fmtCustom || fmtSel };
        }
    };
}

/** 属性对话框·下拉选项块 (slot.opts): 选项逐行编辑 + 单选/复选 + 默认选中勾选
 *  默认选中 = 模板勾选 (新建文书时自动填入; 单选/复选均可再取消), 替代旧「默认内容」 */
function emrPropOptsBlock(item) {
    const isMulti = !!item.multi;
    let picked = Array.isArray(item.defPick) ? item.defPick.slice() : [];
    const html = '<div class="form-group"><label class="form-label">选项内容（每行一个，也可用顿号/逗号分隔）</label>'
        + '<textarea id="prop-opts" class="form-control" rows="5" placeholder="承认&#10;否认&#10;不详">' + Format.escape(item.opts.join('\n')) + '</textarea></div>'
        + '<div class="form-group"><label class="form-label">选择类型</label><div class="emr-prop-radios">'
        + [['single', '单选（下拉选择）'], ['multi', '复选（多选、顿号拼接）']].map(function (m) {
            const on = (m[0] === 'multi') === isMulti;
            return '<label class="emr-prop-radio"><input type="radio" name="sel-type" value="' + m[0] + '"' + (on ? ' checked' : '') + '> ' + m[1] + '</label>';
        }).join('') + '</div></div>'
        + '<div class="form-group"><label class="form-label">默认选中（新建文书时自动填入，可取消；不勾选则不预填）</label>'
        + '<div id="prop-defpick" class="emr-prop-test"></div></div>';
    const isMultiNow = (sub) => {
        const sel = sub.querySelector('input[name="sel-type"]:checked');
        return !!(sel && sel.value === 'multi');
    };
    return {
        html: html,
        bind: function (sub) {
            const box = sub.querySelector('#prop-defpick');
            const renderPick = function () {
                const lines = emrParseOpts(sub.querySelector('#prop-opts').value);
                if (!lines.length) { box.innerHTML = '<span class="emr-prop-testempty">暂无选项</span>'; return; }
                box.innerHTML = lines.map(function (o2) {
                    return '<label class="emr-prop-radio"><input type="checkbox" value="' + Format.escape(o2) + '"' + (picked.indexOf(o2) >= 0 ? ' checked' : '') + '> ' + Format.escape(o2) + '</label>';
                }).join('');
                box.querySelectorAll('input').forEach(function (inp) {
                    inp.addEventListener('change', function () {
                        if (inp.checked) {
                            if (isMultiNow(sub)) {
                                if (picked.indexOf(inp.value) < 0) picked.push(inp.value);
                            } else {
                                picked = [inp.value]; /* 单选: 仅保留一项 */
                                box.querySelectorAll('input').forEach(function (x) { x.checked = x === inp; });
                            }
                        } else {
                            picked = picked.filter(function (x) { return x !== inp.value; });
                        }
                    });
                });
            };
            sub.querySelector('#prop-opts').addEventListener('input', renderPick);
            sub.querySelectorAll('input[name="sel-type"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    if (!isMultiNow(sub) && picked.length > 1) picked = picked.slice(0, 1);
                    box.querySelectorAll('input').forEach(function (x) { x.checked = picked.indexOf(x.value) >= 0; });
                });
            });
            renderPick();
        },
        read: function (sub) {
            const lines = emrParseOpts(sub.querySelector('#prop-opts').value);
            const multi = isMultiNow(sub);
            let dp = picked.filter(function (x) { return lines.indexOf(x) >= 0; });
            if (!multi) dp = dp.slice(0, 1);
            return { opts: lines, multi: multi, defPick: dp };
        }
    };
}

/** 元素属性对话框 (通用): 名称/内容/默认内容/默认占位符/是否必选 + 类型专属(日期/下拉选项) */
function emr_dsnShowProps(item, p, o) {
    const TYPE_NAME = { fx: '固定文字', slot: '填写字段', ref: '字段引用', dyn: '动态项' };
    const parts = [];
    const dtBlk = (item.t === 'slot' && item.dt) ? emrPropDtBlock(item) : null;
    const optsBlk = (item.t === 'slot' && Array.isArray(item.opts)) ? emrPropOptsBlock(item) : null;

    parts.push('<div class="form-group"><label class="form-label">元素名称（识别用，可留空）</label>'
        + '<input id="prop-name" class="form-control" value="' + Format.escape(item.name || '') + '" placeholder="' + Format.escape(emr_dsnItemName(item)) + '"></div>');
    if (item.t === 'fx') {
        parts.push('<div class="form-group"><label class="form-label">元素内容（固定显示、日常不可删除）</label>'
            + '<input id="prop-fx-text" class="form-control" value="' + Format.escape(item.text || '') + '"></div>');
    }
    if (item.t === 'slot') {
        parts.push('<div class="form-group"><label class="form-label">占位描述（未填写时显示的灰色提示，打印不可见）</label>'
            + '<input id="prop-ph" class="form-control" value="' + Format.escape(item.ph || '') + '" placeholder="如：请输入 / 请选择日期和时间"></div>');
        /* 下拉/复选等可供选择的项目: 默认值改为在下方选项里勾选 (不再使用「默认内容」预填) */
        if (!Array.isArray(item.opts)) {
            parts.push('<div class="form-group"><label class="form-label">默认内容（新建文书时自动预填，可打印、可修改）</label>'
                + '<input id="prop-def" class="form-control" value="' + Format.escape(item.def || '') + '" placeholder="可留空"></div>');
        }
        parts.push('<div class="form-group"><label class="emr-prop-radio"><input type="checkbox" id="prop-req"' + (item.req ? ' checked' : '') + '> 是否必选（未完成时红色下划线标记，且无法签名）</label></div>');
        if (dtBlk) parts.push(dtBlk.html);
        if (optsBlk) parts.push(optsBlk.html);
    }
    if (item.t === 'ref') {
        parts.push('<div class="form-group"><label class="form-label">默认占位符（取不到值时显示，打印不可见）</label>'
            + '<input id="prop-fb" class="form-control" value="' + Format.escape(item.fb || '＿＿') + '"></div>');
    }
    if (item.t === 'dyn') {
        parts.push('<div class="form-group"><label class="form-label">说明</label><p class="emr-tpl-hint">动态项内容由系统自动填充（' + Format.escape(emr_dynHint(item.key)) + '），书写时不可手动编辑。</p></div>');
    }
    const sub = Dom.modal('元素属性 · ' + (TYPE_NAME[item.t] || '元素'), parts.join(''), [
        { text: '确定', type: 'primary', onclick: function () {
            const name = (sub.querySelector('#prop-name').value || '').trim();
            if (name) item.name = name; else delete item.name;
            if (item.t === 'fx') {
                const tv = (sub.querySelector('#prop-fx-text').value || '').trim();
                if (!tv) { Dom.toast('元素内容不能为空', 'error'); return false; }
                item.text = tv;
            }
            if (item.t === 'slot') {
                const ph = (sub.querySelector('#prop-ph').value || '').trim();
                if (ph) item.ph = ph; else delete item.ph;
                const defEl = sub.querySelector('#prop-def');
                if (defEl) {
                    const def = (defEl.value || '').trim();
                    if (def) item.def = def; else delete item.def;
                }
                if (sub.querySelector('#prop-req').checked) item.req = true; else delete item.req;
                if (dtBlk) item.dt = dtBlk.read(sub);
                if (optsBlk) {
                    const r0 = optsBlk.read(sub);
                    item.opts = r0.opts;
                    if (r0.multi) item.multi = true; else delete item.multi;
                    if (r0.defPick.length) item.defPick = r0.defPick; else delete item.defPick;
                }
            }
            if (item.t === 'ref') item.fb = (sub.querySelector('#prop-fb').value || '').trim() || '＿＿';
            if (_emrEditor) _emrEditor.refresh();
            Dom.toast('属性已更新', 'success');
        } },
        { text: '取消', type: 'default' }
    ]);
    if (dtBlk) dtBlk.bind(sub);
    if (optsBlk) optsBlk.bind(sub);
}

/** 设计工具: 插入字段 / 下拉字段(单选/复选) / 标题 / 引用
 *  下拉/复选: 可不含固定标签 (与文字混排), 选项每行一个, 占位描述可自定义 */
function emr_dsnInsertField(withSelect, multi) {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const title = withSelect ? (multi ? '插入复选框字段' : '插入下拉字段') : '插入字段';
    const content = '<div class="form-group"><label class="emr-prop-radio"><input type="checkbox" id="dsn-label-on" checked> 包含固定标签（在字段前显示文字标签，如「职业史：」）</label></div>'
        + '<div class="form-group" id="dsn-label-row"><label class="form-label">固定标签文字</label>'
        + '<input id="dsn-label" class="form-control" placeholder="如：职业史"></div>'
        + (withSelect
            ? '<div class="form-group"><label class="form-label">选项（每行一个，也可用顿号/逗号分隔；可留空稍后右击配置）</label>'
              + '<textarea id="dsn-opts" class="form-control" rows="4" placeholder="承认&#10;否认&#10;不详"></textarea></div>'
              + '<div class="form-group"><label class="form-label">占位描述（未选择时显示，打印不可见）</label>'
              + '<input id="dsn-ph" class="form-control" value="请选择" placeholder="如：请选择处置措施（可留空）"></div>'
            : '<div class="form-group"><label class="form-label">占位描述（未填写时显示，打印不可见）</label>'
              + '<input id="dsn-ph" class="form-control" placeholder="默认：请输入+标签（可留空）"></div>')
        + (withSelect ? '<p class="emr-tpl-hint">下拉框为独立元素：取消勾选固定标签后可与其他文字混排（如「下拉框」高血压病病史）。</p>' : '')
        + (multi ? '<p class="emr-tpl-hint">复选：书写时可勾选多项，自动以顿号拼接（如 承认、否认）</p>' : '');
    const sub = Dom.modal(title, content, [
        { text: '插入', type: 'primary', onclick: function () {
            const withLabel = sub.querySelector('#dsn-label-on').checked;
            const label = (sub.querySelector('#dsn-label').value || '').trim();
            if (withLabel && !label) { Dom.toast('请输入固定标签文字（或取消勾选「包含固定标签」）', 'error'); return false; }
            const select = withSelect ? emrParseOpts(sub.querySelector('#dsn-opts').value) : [];
            const ph = (sub.querySelector('#dsn-ph').value || '').trim();
            _emrEditor.insertField(label, {
                withLabel: withLabel,
                select: withSelect ? select : null,
                multi: !!multi,
                ph: ph || undefined
            });
        } },
        { text: '取消', type: 'default' }
    ]);
    const onEl = sub.querySelector('#dsn-label-on');
    const labelRow = sub.querySelector('#dsn-label-row');
    const updLabelRow = function () { labelRow.style.display = onEl.checked ? '' : 'none'; };
    onEl.addEventListener('change', updLabelRow);
    updLabelRow();
    sub.querySelector('#dsn-label').focus();
}
function emr_dsnInsertHeading() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const content = '<div class="form-group"><label class="form-label">标题文字</label><input id="dsn-h" class="form-control" placeholder="如：体格检查"></div>';
    const sub = Dom.modal('插入标题', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const t = (document.getElementById('dsn-h').value || '').trim();
            if (!t) { Dom.toast('请输入标题文字', 'error'); return false; }
            _emrEditor.insertHeading(t);
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#dsn-h').focus();
}
function emr_dsnInsertRef() {
    if (!_emrEditor) return;
    if (!_emrEditor.getCaretPoint()) { Dom.toast('请先在页面中点击要插入的位置', 'error'); return; }
    const content = '<div class="form-group"><label class="form-label">字段键（其他文书的字段标识）</label>'
        + '<input id="dsn-ref" class="form-control" list="dsn-ref-list" placeholder="如：chief_complaint_val（主诉）">'
        + '<datalist id="dsn-ref-list"><option value="p_name">姓名</option><option value="p_age">年龄</option><option value="p_gender">性别</option><option value="chief_complaint_val">主诉</option><option value="present_illness_val">现病史</option><option value="past_history_val">既往史</option><option value="special_exam_val">专科检查</option><option value="aux_exam_val">辅助检查</option><option value="vital_bmi_value">BMI</option></datalist></div>'
        + '<div class="form-group"><label class="form-label">无值时的占位文字</label><input id="dsn-ref-fb" class="form-control" value="＿＿"></div>';
    const sub = Dom.modal('插入字段引用', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const k = (document.getElementById('dsn-ref').value || '').trim();
            const fb = (document.getElementById('dsn-ref-fb').value || '＿＿').trim();
            if (!k) { Dom.toast('请输入字段键', 'error'); return false; }
            _emrEditor.insertRef(k, fb);
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#dsn-ref').focus();
}

/** 科室显示名: 全院通用(NULL) / 实时科室名 (id 关联, 随管理员更名同步) / 已删除科室兜底 */
function emrDeptNameOf(t) {
    if (t.department_id == null) return '全院通用';
    return t.department_name ? t.department_name : ('科室#' + t.department_id + '（已弃用）');
}

</script>
