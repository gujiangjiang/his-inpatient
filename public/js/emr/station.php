<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree">
        <div class="emr-tree-header" id="emr-tree-header">病历文书</div>
        <div id="emr-category-tree" class="emr-category-tree"></div>
        <div id="emr-design-panel" class="emr-dsn-panel" style="display:none">
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-field"><span class="emr-dsn-caret"></span>插入字段</div>
                <div class="emr-dsn-sub" id="dsn-fields" style="display:none"></div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-fixed"><span class="emr-dsn-caret"></span>固定元素</div>
                <div class="emr-dsn-sub" id="dsn-fixed" style="display:none"></div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-diag"><span class="emr-dsn-caret"></span>诊断引用</div>
                <div class="emr-dsn-sub" id="dsn-diag" style="display:none">
                    <div class="emr-dsn-field" data-diag="admission">入院诊断（写作时自动填入）</div>
                    <div class="emr-dsn-field" data-diag="discharge">出院诊断（写作时自动填入）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-table"><span class="emr-dsn-caret"></span>插入表格</div>
                <div class="emr-dsn-sub" id="dsn-table" style="display:none">
                    <div class="emr-tblpick" id="emr-tblpick"></div>
                    <div class="emr-tblpick-label" id="emr-tblpick-label">移入选择行列（拖动框线可调列宽/行高）</div>
                </div>
            </div>
            <div class="emr-dsn-sec">
                <div class="emr-dsn-head" id="dsn-sec-select"><span class="emr-dsn-caret"></span>插入下拉字段</div>
                <div class="emr-dsn-sub" id="dsn-select" style="display:none">
                    <div class="emr-dsn-field" data-sel="single">单选框（下拉选择）</div>
                    <div class="emr-dsn-field" data-sel="multi">复选框（多选、顿号拼接）</div>
                </div>
            </div>
            <div class="emr-dsn-item" id="btn-dsn-heading">插入标题</div>
            <div class="emr-dsn-item" id="btn-dsn-ref">插入字段引用</div>
        </div>
        <div class="emr-tree-foot" id="emr-tree-foot">
            <button type="button" class="btn btn-small" id="btn-template-mgr">模板</button>
            <button type="button" class="btn btn-small" id="btn-content-mgr">内容</button>
        </div>
    </div>
    <!-- 二级主视图: 字处理病历编辑区 (类 Word 整页画布) -->
    <div class="emr-editor-pane">
        <div id="emr-editor-empty" class="emr-editor-empty">
            <p>请从左侧选择病历文书，或点击分类后的 <b>＋</b> 从模板新建</p>
        </div>
        <div id="emr-editor" class="emr-editor" style="display:none">
            <div class="emr-toolbar" id="emr-toolbar">
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
            </div>
            <div class="emr-toolbar" id="emr-design-bar" style="display:none">
                <span class="emr-design-name" id="emr-design-name"></span>
                <span class="emr-dsn-tip">从左侧「模板设计」面板插入字段与元素；右击正文元素可修改属性</span>
                <span class="emr-tb-spacer"></span>
                <button type="button" class="btn btn-small btn-success" id="btn-dsn-save">保存模板</button>
                <button type="button" class="btn btn-small" id="btn-dsn-exit">退出设计</button>
            </div>
            <div id="emr-editor-scroll" class="emr-editor-scroll">
                <div class="emr-editor-page" id="emr-editor-page">
                    <div id="emr-editor-canvas"></div>
                </div>
            </div>
            <div id="emr-editor-status" class="emr-statusbar"><span id="emr-doc-status" class="emr-doc-status"></span><span id="emr-sb-savetag" class="emr-sb-saved">已保存</span><span id="emr-save-state" class="emr-sb-state"></span><button type="button" class="emr-refp-btn" id="btn-ref-panel" title="引用病历/检验/检查/医嘱/既往/内容">引用</button><span id="emr-sb-count" class="emr-sb-count">字数 0</span></div>
            <!-- 引用面板: 从状态栏向上弹出 (病历/检验/检查/医嘱/既往/内容) -->
            <div id="emr-ref-panel" class="emr-refp" style="display:none">
                <div class="emr-refp-head">
                    <div class="emr-refp-tabs">
                        <span class="emr-refp-tab active" data-tab="doc">病历</span>
                        <span class="emr-refp-tab" data-tab="lab">检验</span>
                        <span class="emr-refp-tab" data-tab="exam">检查</span>
                        <span class="emr-refp-tab" data-tab="order">医嘱</span>
                        <span class="emr-refp-tab" data-tab="past">既往</span>
                        <span class="emr-refp-tab" data-tab="content">内容</span>
                    </div>
                    <button type="button" class="btn btn-small btn-primary" id="emr-refp-write">写回病历</button>
                    <button type="button" class="btn btn-small" id="emr-refp-close">收起</button>
                </div>
                <div class="emr-refp-body">
                    <div class="emr-refp-left" id="emr-refp-left"></div>
                    <div class="emr-refp-right" id="emr-refp-right">
                        <div class="emr-refp-empty">从左侧选择一条记录，右侧显示内容；可全选或选中部分文字后点击「写回病历」</div>
                    </div>
                </div>
            </div>
            <div id="emr-signature-footer" class="emr-signature-footer" style="display:none"></div>
        </div>
    </div>
</div>

<script>
var _emrCategories = [];
var _emrDocs = [];
var _emrCurrentDoc = null;
var _emrEditMode = true;
var _emrEditor = null;       // EmrEditor 实例
var _emrAutoSaveTimer = null;
var _emrPatient = null;
var _emrQc = [];              // 质控提醒 [{cat, code, msg, hours}]
var _emrDesign = null;        // 模板设计状态 {id, name, categoryId, recordType}
var _emrCategories = _emrCategories || [];

/* ================= 初始化 / 树 ================= */
async function emr_station_init() {
    _emrPatient = PatientContext.current();
    if (!_emrPatient) { Dom.toast('请先选择患者', 'error'); return; }
    await loadCategories();
    await loadDocuments();
    await loadQc();
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

/** 质控提醒加载 (入院24h/首次病程8h/出院小结) */
async function loadQc() {
    try {
        const r = await AjaxLoader.api('emr/qc-status?patient_id=' + _emrPatient.id, { silent: true });
        _emrQc = (r.data && r.data.items) || [];
    } catch (e) { _emrQc = []; }
}

/** 文书列表与质控统一刷新 */
async function refreshDocs() {
    await loadDocuments();
    await loadQc();
    renderCategoryTree();
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
        const qcN = _emrQc.filter(q => q.cat == cat.id).length;
        header.innerHTML = '<span class="emr-cat-name">' + Format.escape(cat.name) + '</span>' +
            (qcN ? '<span class="emr-cat-qc" title="质控提醒（点击查看）">' + qcN + '</span>' : '') +
            '<span class="emr-cat-count">' + docs.length + '</span>' +
            '<span class="emr-cat-add" title="从模板新建" onclick="emr_openTemplatePicker(' + cat.id + ')">＋</span>';
        const qcEl = header.querySelector('.emr-cat-qc');
        if (qcEl) qcEl.onclick = (ev) => { ev.stopPropagation(); emr_showQc(cat.id); };
        group.appendChild(header);
        const docList = document.createElement('div');
        docList.className = 'emr-doc-list';
        docs.forEach(d => {
            const item = document.createElement('div');
            item.className = 'emr-doc-item' + (d.status === 'signed' ? ' signed' : '');
            const fullTitle = d.title || d.template_name || '未命名文书';
            item.innerHTML = '<span class="emr-doc-title-text" title="' + Format.escape(fullTitle) + '">' + Format.escape(fullTitle) + '</span>' +
                '<span class="emr-doc-time">' + Format.escape(emrStamp(d.updated_at || d.created_at || '')) + '</span>' +
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
/** 模板选择器: 本科室 / 全院 两个大 Tab; 全院按科室分组; 列表限高滚动
 *  选项: 日期时间(统一可选, 联动记录日期) / 上级医师 / 职称 (仅"上级医师查房记录"启用且必选,
 *  文书名称 = 上级医师 + 职称 + 查房记录) */
async function emr_openTemplatePicker(categoryId) {
    const r = await AjaxLoader.api('emr/template-list?category_id=' + categoryId + '&structured=1', { silent: true });
    const templates = r.data || [];
    if (templates.length === 0) { Dom.toast('该分类下暂无可用模板', 'error'); return; }
    const myDeptId = Auth.user && Auth.user.department_id ? String(Auth.user.department_id) : null;
    /* 分组: key=科室id (null → '0' 全院通用) */
    const groups = new Map();
    templates.forEach(function (t) {
        const key = t.department_id == null ? '0' : String(t.department_id);
        if (!groups.has(key)) groups.set(key, { name: emrDeptNameOf(t), items: [] });
        groups.get(key).items.push(t);
    });
    const myGroup = myDeptId ? groups.get(myDeptId) : null;

    function itemHTML(t) {
        return '<div class="emr-tpl-item" data-id="' + t.id + '">' + Format.escape(t.name) + '</div>';
    }
    function mineHTML() {
        if (!myGroup) return '<div class="emr-tpl-empty">本科室暂无可用模板：可在"全院"中选用，或点击左下角"模板"创建本科室模板</div>';
        return '<div class="emr-tpl-group"><div class="emr-tpl-ghead open" data-g="mine"><span class="emr-tpl-gname">'
            + Format.escape(myGroup.name) + '</span><span class="emr-tpl-gcount">' + myGroup.items.length
            + '</span><span class="emr-tpl-gcaret"></span></div><div class="emr-tpl-gitems">'
            + myGroup.items.map(itemHTML).join('') + '</div></div>';
    }
    function allHTML() {
        return Array.from(groups.keys()).map(function (k) {
            const g = groups.get(k);
            const open = (k === '0' || k === myDeptId);
            return '<div class="emr-tpl-group"><div class="emr-tpl-ghead' + (open ? ' open' : '') + '" data-g="' + k + '"><span class="emr-tpl-gname">'
                + Format.escape(g.name) + '</span><span class="emr-tpl-gcount">' + g.items.length
                + '</span><span class="emr-tpl-gcaret"></span></div><div class="emr-tpl-gitems"' + (open ? '' : ' style="display:none"') + '>'
                + g.items.map(itemHTML).join('') + '</div></div>';
        }).join('');
    }

    const nowStamp = emrNowStamp();
    const content = '<div class="emr-tpl-picker">'
        + '<div class="emr-tpl-tabs"><span class="emr-tpl-tab active" data-tab="mine">本科室</span><span class="emr-tpl-tab" data-tab="all">全院</span></div>'
        + '<div class="emr-tpl-list" id="emr-tpl-list">' + mineHTML() + '</div>'
        + '<div class="emr-tpl-opts">'
        + '<div class="emr-tpl-opt"><label class="form-label">日期时间</label><input id="emr-tpl-dt" class="form-control" data-dtpicker="datetime" value="' + nowStamp + '"></div>'
        + '<div class="emr-tpl-opt"><label class="form-label">上级医师</label><select id="emr-tpl-senior" class="form-control" data-spicker="off" disabled><option value="">请选择</option></select></div>'
        + '<div class="emr-tpl-opt"><label class="form-label">职称</label><select id="emr-tpl-title" class="form-control" data-spicker="off" disabled><option value="">请选择</option><option>主任医师</option><option>副主任医师</option><option>主治医师</option><option>住院医师</option></select></div>'
        + '</div>'
        + '<div class="emr-tpl-foot"><button type="button" class="btn btn-primary" id="emr-tpl-ok">确定</button><button type="button" class="btn" id="emr-tpl-cancel">取消</button></div>'
        + '</div>';
    const overlay = Dom.modal('病历模板选择器', content, []);
    const listEl = overlay.querySelector('#emr-tpl-list');
    let selected = null;
    let doctorsLoaded = false;

    function bindList() {
        listEl.querySelectorAll('.emr-tpl-ghead').forEach(function (h) {
            h.addEventListener('click', function () {
                const items = h.nextElementSibling;
                const open = h.classList.toggle('open');
                items.style.display = open ? '' : 'none';
            });
        });
        listEl.querySelectorAll('.emr-tpl-item').forEach(function (it) {
            it.addEventListener('click', function () {
                listEl.querySelectorAll('.emr-tpl-item').forEach(function (x) { x.classList.remove('selected'); });
                it.classList.add('selected');
                selected = templates.find(function (t) { return String(t.id) === String(it.dataset.id); });
                const isSeniorRound = !!(selected && selected.name.indexOf('上级') >= 0 && selected.name.indexOf('查房') >= 0);
                const seniorSel = overlay.querySelector('#emr-tpl-senior');
                const titleSel = overlay.querySelector('#emr-tpl-title');
                seniorSel.disabled = !isSeniorRound;
                titleSel.disabled = !isSeniorRound;
                if (isSeniorRound && !doctorsLoaded) {
                    doctorsLoaded = true;
                    AjaxLoader.api('emr/dept-doctors', { silent: true }).then(function (dr) {
                        (dr.data || []).forEach(function (d) {
                            const o = document.createElement('option');
                            o.value = d.name; o.textContent = d.name;
                            seniorSel.appendChild(o);
                        });
                    });
                }
            });
        });
    }
    overlay.querySelectorAll('.emr-tpl-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            overlay.querySelectorAll('.emr-tpl-tab').forEach(function (x) { x.classList.remove('active'); });
            tab.classList.add('active');
            listEl.innerHTML = tab.dataset.tab === 'mine' ? mineHTML() : allHTML();
            bindList();
        });
    });
    bindList();
    if (window.DateTimePicker) DateTimePicker.initInputs(overlay);
    overlay.querySelector('#emr-tpl-cancel').addEventListener('click', function () { overlay.remove(); });
    overlay.querySelector('#emr-tpl-ok').addEventListener('click', function () {
        if (!selected) { Dom.toast('请选择模板', 'error'); return; }
        const isSeniorRound = /上级/.test(selected.name) && /查房/.test(selected.name);
        const dtVal = (overlay.querySelector('#emr-tpl-dt').value || '').trim();
        const senior = overlay.querySelector('#emr-tpl-senior').value;
        const pos = overlay.querySelector('#emr-tpl-title').value;
        let title = selected.name;
        const meta = {};
        if (isSeniorRound) {
            if (!senior || !pos) { Dom.toast('请选择上级医师与职称', 'error'); return; }
            title = senior + pos + '查房记录';
            meta.senior_name = senior;
            meta.senior_title = pos;
        }
        overlay.remove();
        emr_createFromTemplate(selected.id, categoryId, { datetime: dtVal || nowStamp, title: title, meta: meta });
    });
}

function parseSchema(json) {
    if (!json) return null;
    try { const o = JSON.parse(json); return o && typeof o === 'object' ? o : null; } catch (e) { return null; }
}

async function emr_createFromTemplate(templateId, categoryId, opts) {
    opts = opts || {};
    const tr = await AjaxLoader.api('emr/template-get?id=' + templateId, { silent: true });
    const template = tr.data;
    if (!template) { Dom.toast('模板数据异常', 'error'); return; }
    const schema = parseSchema(template.schema_json);
    if (!schema) { Dom.toast('模板 schema 异常', 'error'); return; }

    const doc = (schema.mode === 'word')
        ? EmrDoc.fromSchema(schema)
        : EmrDoc.fromSections(schema.sections || [], template.name);
    doc.title = opts.title || template.name;
    /* 槽位「默认内容」预填 (元素属性配置; 非占位符, 可打印可修改) */
    EmrOps.walkSlots(doc.body, function (p, slot) {
        if (slot.def && !EmrOps.valueAfterSlot(p, slot.key).trim()) EmrOps.setSlotValue(p, slot.key, slot.def);
    });
    /* 记录日期 = 模板选择时指定的日期时间 (默认当前时间; 可点击修改; 不参与首页同步) */
    EmrOps.setSlotValueByKey(doc.body, 'p_recorddate_val', opts.datetime || emrNowStamp());
    /* 字段引用: 仅在首次创建时自动调用一次 (保存即固化, 之后打开不再变化) */
    try { await resolveRefFields(doc); } catch (e) { console.error('字段引用解析失败', e); }
    const meta = Object.assign({ guard: EmrDoc.collectGuard(doc.body) }, opts.meta || {});
    let contentHtml = '';
    try { contentHtml = EmrPrint.buildHTML(doc, buildPatientInfo()); } catch (e) { console.error('打印快照生成失败', e); }

    const r = await AjaxLoader.api('emr/document-save', { method: 'POST', data: {
        patient_id: _emrPatient.id, category_id: categoryId, template_id: templateId,
        title: doc.title, doc_meta: meta,
        doc_json: JSON.stringify(doc), content_html: contentHtml
    }});
    Dom.toast('已新建草稿文书', 'success');
    await refreshDocs();
    emr_openDocument(r.data.id);
}

/* ================= 患者信息 / 页面参数 ================= */
function buildPatientInfo() {
    const p = _emrPatient || PatientContext.current();
    if (!p) return {};
    const age = p.birth_date ? Math.max(0, new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
    return {
        name: p.name || '', gender: Format.gender(p.gender), age: String(age),
        department: p.department_name || '', ward_name: p.ward_name || '',
        bed_no: p.bed_no || '', admission_no: p.admission_no || ''
    };
}

function applyPageCss(page) {
    const el = document.getElementById('emr-editor-page');
    const p = page || {};
    const px = (v, dflt) => ((v == null ? dflt : v) * 96 / 72) + 'px';
    el.style.setProperty('--emr-ml', px(p.ml, 36));
    el.style.setProperty('--emr-mr', px(p.mr, 36));
    el.style.setProperty('--emr-mt', px(p.mt, 40));
    el.style.setProperty('--emr-mb', px(p.mb, 36));
    el.style.setProperty('--emr-hh', px(p.header_h, 16));
    el.style.setProperty('--emr-font', p.font || '仿宋_GB2312, FangSong, SimSun, serif');
    el.style.setProperty('--emr-size', px(p.sz, 12));
    el.style.setProperty('--emr-line', String(p.line || 1.5));
}

/* ================= 打开文书 (统一字处理编辑器) ================= */
async function emr_openDocument(docId) {
    let r;
    try {
        r = await AjaxLoader.api('emr/document-get?id=' + docId, { silent: true });
    } catch (e) {
        Dom.toast('打不开文书: ' + (e && e.message ? e.message : '未知错误'), 'error');
        return;
    }
    const doc = r.data;
    _emrCurrentDoc = doc;
    document.getElementById('emr-ref-panel').style.display = 'none'; /* 切换文书: 收起引用面板 */
    _emrRefCache = {};
    if (_emrEditor) { _emrEditor.destroy(); _emrEditor = null; }

    document.getElementById('emr-editor-empty').style.display = 'none';
    document.getElementById('emr-editor').style.display = 'flex';

    // 兜底: 旧数据未转换时 (迁移 038 正常已处理), 客户端转换后按字处理打开, 保存时落库
    if (!doc.doc_json) {
        doc.doc_json = EmrDoc.fromSections(doc.sections || [], doc.title);
        doc.doc_meta = doc.doc_meta || {};
        if (!doc.doc_meta.guard) doc.doc_meta.guard = EmrDoc.collectGuard(doc.doc_json.body);
    }
    await openWordDoc(doc, doc.status === 'signed');
}

/* ================= 病案首页单项同步 (仅编辑状态; 已签名=法律快照不动) ================= */
/** 时间戳 'YYYY-MM-DD HH:mm' */
function emrNowStamp() {
    const d = new Date();
    const p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
}
/** 取日期部分 'YYYY-MM-DD' */
function emrYMD(v) {
    if (!v) return '';
    const m = String(v).match(/(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
    return m ? m[1] + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0') : String(v).slice(0, 10);
}
/** 日期+时间 'YYYY-MM-DD HH:mm' (无时间部分时仅日期) */
function emrStamp(v) {
    if (!v) return '';
    const m = String(v).match(/(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2}))?/);
    if (!m) return String(v);
    const d = m[1] + '-' + m[2].padStart(2, '0') + '-' + m[3].padStart(2, '0');
    return m[4] != null ? d + ' ' + m[4].padStart(2, '0') + ':' + m[5].padStart(2, '0') : d;
}
/* 固化字段 (dyn 只读): 用户不可修改, 始终自动跟随病案首页 */
var CASE_DYN = {
    p_name: (c, p) => c.name || (p && p.name) || '',
    p_gender: (c, p) => ({ '1': '男', '2': '女' }[c.gender]) || (p && p.gender === 'male' ? '男' : p && p.gender === 'female' ? '女' : (p && (p.gender === '男' || p.gender === '女') ? p.gender : '')),
    p_age: (c, p) => {
        if (c.age_y) return String(c.age_y);
        if (p && p.birth_date) { const a = new Date().getFullYear() - new Date(p.birth_date).getFullYear(); return a > 0 ? String(a) : ''; }
        return '';
    },
};
/* 可改字段 (slot): 未手动修改时自动跟随首页; 手动修改并保存后冻结 */
var CASE_SYNC = {
    p_marriage_val: (c) => ({ '1': '未婚', '2': '已婚', '3': '丧偶', '4': '离异' }[c.marriage] || ''),
    p_native_val: (c) => [c.native_prov, c.native_city].filter(x => x && String(x).trim()).join(''),
    p_nation_val: (c) => c.nation || '',
    p_occupation_val: (c) => c.occupation || '',
    p_workunit_val: (c) => c.work_unit || '',
    p_address_val: (c) => [c.addr_prov, c.addr_city, c.addr_county, c.addr_detail].filter(x => x && String(x).trim()).join(''),
    p_admitdate_val: (c, p) => emrStamp((p && p.admission_date) || c.admit_time || ''),
    /* p_teller_val: 无首页来源, 不参与同步 */
    /* p_recorddate_val: 建档时间, 由创建流程写入, 可点选修改 */
};
/** 入院录编辑状态: 未手动修改过的同步字段自动取病案首页最新值 (空值回退占位; 单向仅读) */
async function syncFromFrontPage(doc) {
    let hasKey = false;
    for (const k in CASE_SYNC) { if (EmrOps.findParaWithItem(doc.body, 'slot', k)) { hasKey = true; break; } }
    if (!hasKey) { for (const k in CASE_DYN) { if (EmrOps.findParaWithItem(doc.body, 'dyn', k)) { hasKey = true; break; } } }
    if (!hasKey) return;
    let c = {};
    try {
        const r = await AjaxLoader.api('case/get?admission_no=' + encodeURIComponent(_emrPatient.admission_no), { silent: true });
        c = (r.data && r.data.data) || {};
    } catch (e) { c = {}; }
    for (const key in CASE_SYNC) {
        const p = EmrOps.findParaWithItem(doc.body, 'slot', key);
        if (!p) continue;
        const it = p.runs.find(x => x.t === 'slot' && x.key === key);
        if (!it || it.manual) continue;
        const val = CASE_SYNC[key](c, _emrPatient);
        if (val === undefined) continue;
        EmrOps.setSlotValueByKey(doc.body, key, val || '');
    }
    /* 固化字段: 无条件跟随首页 (不可手动修改) */
    for (const key in CASE_DYN) {
        if (!EmrOps.findParaWithItem(doc.body, 'dyn', key)) continue;
        const val = CASE_DYN[key](c, _emrPatient);
        if (val === undefined) continue;
        EmrOps.setDynTextByKey(doc.body, key, val || '');
    }
}

/** 诊断引用元素自动填充: diag_admission/diag_discharge dyn 取左侧「诊断」模块 (未签文档每次打开刷新) */
async function syncDiagDyns(doc) {
    const need = ['diag_admission', 'diag_discharge'].filter(function (k) {
        return EmrOps.findParaWithItem(doc.body, 'dyn', k);
    });
    if (!need.length) return;
    let rows = [];
    try {
        const r = await AjaxLoader.api('emr/diagnosis-list?patient_id=' + _emrPatient.id, { silent: true });
        rows = r.data || [];
    } catch (e) { return; }
    const join = function (cat) {
        const list = rows.filter(function (x) { return x.category === cat; });
        if (!list.length && cat === 'admission' && _emrPatient && _emrPatient.admission_diagnosis) {
            return String(_emrPatient.admission_diagnosis); /* 诊断模块未录入时兜底: 患者入院诊断 */
        }
        return list.map(function (x, i) { return list.length > 1 ? (i + 1) + '.' + x.name : x.name; }).join('；');
    };
    if (need.indexOf('diag_admission') >= 0) EmrOps.setDynTextByKey(doc.body, 'diag_admission', join('admission'));
    if (need.indexOf('diag_discharge') >= 0) EmrOps.setDynTextByKey(doc.body, 'diag_discharge', join('discharge'));
}

/** 字段引用解析: 【姓名】【主诉】等自动调用患者其他文书里的字段值
 *  规则: 仅在【首次创建】文书时调用一次; 保存后即为固化快照, 再次打开不再变化
 *  (如需重新取值: 删除该文书后重新创建) */
async function resolveRefFields(doc) {
    const keys = [];
    EmrDoc.walk(doc.body, (b) => {
        if (b.t === 'p') for (const it of b.runs) if (it.t === 'ref' && it.key && keys.indexOf(it.key) < 0) keys.push(it.key);
    });
    if (!keys.length) return;
    try {
        const r = await AjaxLoader.api('emr/field-values?patient_id=' + _emrPatient.id, { silent: true });
        const f = (r.data && r.data.fields) || {};
        for (const k of keys) EmrOps.setRefTextByKey(doc.body, k, f[k] || '');
    } catch (e) { console.error('字段引用解析失败', e); }
}

async function openWordDoc(doc, signed) {
    applyPageCss(doc.doc_json.page);
    _emrEditMode = !signed;
    /* 编辑状态: 患者信息与病案首页单项同步 (已签名=快照, 不动) */
    if (!signed && doc.doc_json && Array.isArray(doc.doc_json.body)) {
        try { await syncFromFrontPage(doc.doc_json); } catch (e) { console.error('病案首页同步失败', e); }
        try { await syncDiagDyns(doc.doc_json); } catch (e) { console.error('诊断引用填充失败', e); }
    }
    const metaGuard = doc.doc_meta && doc.doc_meta.guard && Array.isArray(doc.doc_meta.guard.fx) ? doc.doc_meta.guard : null;
    _emrEditor = EmrEditor.create({
        container: document.getElementById('emr-editor-canvas'),
        doc: doc.doc_json,
        guard: metaGuard || EmrDoc.collectGuard(doc.doc_json.body || []),
        patientInfo: buildPatientInfo(),
        readonly: signed,
        onChange: scheduleAutoSave,
        onStatus: updateEditorStatus,
        diagSync: fetchAdmissionDiagText,
        datePickers: { p_recorddate_val: 'datetime', p_admitdate_val: 'datetime' }
    });
    syncToolbar(signed);
    const sig = document.getElementById('emr-signature-footer');
    if (signed) {
        sig.style.display = 'block';
        sig.innerHTML = '医师签名：' + Format.escape(doc.signer_name || '医师') + '　' + Format.datetime(doc.signed_at);
    } else {
        sig.style.display = 'none';
        sig.innerHTML = '';
    }
}

/* ================= 模板管理 / 设计模式 (#7) ================= */
/** 进入模板设计模式: 编辑器解锁 (可删除固定元素) + 插入工具 + 保存模板 */
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
    const schema = { mode: 'word', title: _emrDesign.name, page: doc.page, header: doc.header, body: doc.body };
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
                _emrEditor.insertItem({
                    t: 'slot', key: 'd' + Date.now().toString(36) + Math.floor(Math.random() * 90 + 10),
                    ph: '请选择', dt: { mode: 'datetime', fmt: '' }
                });
            } else if (el.dataset.fixed === 'text') {
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

/** 设计模式右击表格: 浮动菜单 (表格属性 / 删除表格) */
function emr_dsnTableContext(tblId, x, y) {
    document.querySelectorAll('.emr-ctx-float').forEach(function (m) { m.remove(); });
    const menu = document.createElement('div');
    menu.className = 'emr-context-menu emr-ctx-float';
    menu.style.cssText = 'position:fixed;z-index:5200;left:' + x + 'px;top:' + y + 'px;min-width:130px;';
    menu.innerHTML = '<div class="ctx-item" data-a="props">表格属性…</div><div class="ctx-item danger" data-a="del">删除表格</div>';
    document.body.appendChild(menu);
    const r = menu.getBoundingClientRect();
    if (r.right > window.innerWidth - 8) menu.style.left = (x - r.width) + 'px';
    if (r.bottom > window.innerHeight - 8) menu.style.top = (y - r.height) + 'px';
    const close = function () { menu.remove(); document.removeEventListener('mousedown', onDoc, true); };
    const onDoc = function (ev) { if (!menu.contains(ev.target)) close(); };
    setTimeout(function () { document.addEventListener('mousedown', onDoc, true); }, 0);
    menu.querySelector('[data-a="props"]').addEventListener('click', function () { close(); emr_dsnTableProps(tblId); });
    menu.querySelector('[data-a="del"]').addEventListener('click', function () {
        close();
        Dom.modal('删除表格', '<p>确认从模板中删除整个表格？</p>', [
            { text: '删除', type: 'danger', onclick: function () {
                if (!_emrEditor) return;
                EmrOps.removeBlockById(_emrEditor.getDoc().body, tblId);
                _emrEditor.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
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
    document.querySelectorAll('.emr-ctx-float').forEach(function (m) { m.remove(); });
    const menu = document.createElement('div');
    menu.className = 'emr-context-menu emr-ctx-float';
    menu.style.cssText = 'position:fixed;z-index:5200;left:' + x + 'px;top:' + y + 'px;min-width:130px;';
    let html = '<div class="ctx-item" data-a="props">元素属性…</div>';
    if (EmrDoc.isProtected(item)) html += '<div class="ctx-item danger" data-a="del">删除该固定元素</div>';
    menu.innerHTML = html;
    document.body.appendChild(menu);
    /* 边界收拢: 靠右/下越界时回退 */
    const r = menu.getBoundingClientRect();
    if (r.right > window.innerWidth - 8) menu.style.left = (x - r.width) + 'px';
    if (r.bottom > window.innerHeight - 8) menu.style.top = (y - r.height) + 'px';
    const close = function () { menu.remove(); document.removeEventListener('mousedown', onDoc, true); document.removeEventListener('keydown', onEsc, true); };
    const onDoc = function (ev) { if (!menu.contains(ev.target)) close(); };
    const onEsc = function (ev) { if (ev.key === 'Escape') close(); };
    setTimeout(function () { document.addEventListener('mousedown', onDoc, true); document.addEventListener('keydown', onEsc, true); }, 0);
    menu.querySelector('[data-a="props"]').addEventListener('click', function () {
        close();
        emr_dsnShowProps(item, p, o);
    });
    const del = menu.querySelector('[data-a="del"]');
    if (del) del.addEventListener('click', function () {
        close();
        Dom.modal('删除固定元素', '<p>确认从模板中删除该元素？删除后文书结构中不再保留。</p>', [
            { text: '删除', type: 'danger', onclick: function () { if (_emrEditor) _emrEditor.removeItem(p, item); } },
            { text: '取消', type: 'default' }
        ]);
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

/** 元素属性对话框 (通用): 元素名称/内容/默认内容/默认占位符/是否必选 + 类型专属(日期/下拉选项) */
function emr_dsnShowProps(item, p, o) {
    const TYPE_NAME = { fx: '固定文字', slot: '填写字段', ref: '字段引用', dyn: '动态项' };
    const parts = [];
    parts.push('<div class="form-group"><label class="form-label">元素名称（识别用，可留空）</label>'
        + '<input id="prop-name" class="form-control" value="' + Format.escape(item.name || '') + '" placeholder="' + Format.escape(emr_dsnItemName(item)) + '"></div>');
    if (item.t === 'fx') {
        parts.push('<div class="form-group"><label class="form-label">元素内容（固定显示、日常不可删除）</label>'
            + '<input id="prop-fx-text" class="form-control" value="' + Format.escape(item.text || '') + '"></div>');
    }
    if (item.t === 'slot') {
        parts.push('<div class="form-group"><label class="form-label">默认占位符（灰色提示，打印不可见）</label>'
            + '<input id="prop-ph" class="form-control" value="' + Format.escape(item.ph || '') + '" placeholder="如：请输入"></div>');
        parts.push('<div class="form-group"><label class="form-label">默认内容（新建文书时自动预填，可打印、可修改）</label>'
            + '<input id="prop-def" class="form-control" value="' + Format.escape(item.def || '') + '" placeholder="可留空"></div>');
        parts.push('<div class="form-group"><label class="emr-prop-radio"><input type="checkbox" id="prop-req"' + (item.req ? ' checked' : '') + '> 是否必选（未完成时红色下划线标记，且无法签名）</label></div>');
        if (item.dt) {
            const mode = item.dt.mode || 'datetime';
            const fmt = item.dt.fmt || '';
            const presets = ['', 'yyyy-mm-dd', 'yyyy年mm月dd日', 'yyyy-mm-dd hh:nn', 'yyyy年mm月dd日 hh时nn分', 'hh:nn'];
            parts.push('<div class="form-group"><label class="form-label">日期类型</label><div class="emr-prop-radios">'
                + [['datetime', '日期+时间'], ['date', '仅日期'], ['time', '仅时间']].map(function (m) {
                    return '<label class="emr-prop-radio"><input type="radio" name="dt-mode" value="' + m[0] + '"' + (mode === m[0] ? ' checked' : '') + '> ' + m[1] + '</label>';
                }).join('') + '</div></div>');
            parts.push('<div class="form-group"><label class="form-label">日期格式</label>'
                + '<select id="prop-dt-fmt" class="form-control">' + presets.map(function (x) {
                    return '<option value="' + x + '"' + (fmt === x ? ' selected' : '') + '>' + (x || '默认(按类型)') + '</option>';
                }).join('') + '</select>'
                + '<input id="prop-dt-fmtc" class="form-control" style="margin-top:6px" placeholder="自定义格式（可留空）: yyyy/mm/dd/hh/nn" value="' + (presets.indexOf(fmt) < 0 ? Format.escape(fmt) : '') + '"></div>');
        }
        if (Array.isArray(item.opts)) {
            const isMulti = !!item.multi;
            parts.push('<div class="form-group"><label class="form-label">选项内容（每行一个）</label>'
                + '<textarea id="prop-opts" class="form-control" rows="5" placeholder="无&#10;有&#10;不详">' + Format.escape(item.opts.join('\n')) + '</textarea></div>');
            parts.push('<div class="form-group"><label class="form-label">选择类型</label><div class="emr-prop-radios">'
                + [['single', '单选（下拉选择）'], ['multi', '复选（多选、顿号拼接）']].map(function (m) {
                    const on = (m[0] === 'multi') === isMulti;
                    return '<label class="emr-prop-radio"><input type="radio" name="sel-type" value="' + m[0] + '"' + (on ? ' checked' : '') + '> ' + m[1] + '</label>';
                }).join('') + '</div></div>');
            parts.push('<div class="form-group"><label class="form-label">测试选择（不保存，仅验证交互）</label>'
                + '<div id="prop-test" class="emr-prop-test"></div>'
                + '<div class="emr-prop-testval">结果预览：<b id="prop-test-val">—</b></div></div>');
        }
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
            const name = (document.getElementById('prop-name').value || '').trim();
            if (name) item.name = name; else delete item.name;
            if (item.t === 'fx') {
                const tv = (document.getElementById('prop-fx-text').value || '').trim();
                if (!tv) { Dom.toast('元素内容不能为空', 'error'); return; }
                item.text = tv;
            }
            if (item.t === 'slot') {
                const ph = (document.getElementById('prop-ph').value || '').trim();
                if (ph) item.ph = ph; else delete item.ph;
                const def = (document.getElementById('prop-def').value || '').trim();
                if (def) item.def = def; else delete item.def;
                if (document.getElementById('prop-req').checked) item.req = true; else delete item.req;
                if (item.dt) {
                    const modeSel = sub.querySelector('input[name="dt-mode"]:checked');
                    const fmtSel = (document.getElementById('prop-dt-fmt').value || '').trim();
                    const fmtCustom = (document.getElementById('prop-dt-fmtc').value || '').trim();
                    item.dt = { mode: modeSel ? modeSel.value : 'datetime', fmt: fmtCustom || fmtSel };
                }
                if (Array.isArray(item.opts)) {
                    const lines = (document.getElementById('prop-opts').value || '').split('\n').map(function (x) { return x.trim(); }).filter(Boolean);
                    const sel = sub.querySelector('input[name="sel-type"]:checked');
                    item.opts = lines;
                    if (sel && sel.value === 'multi') item.multi = true; else delete item.multi;
                }
            }
            if (item.t === 'ref') item.fb = (document.getElementById('prop-fb').value || '').trim() || '＿＿';
            if (_emrEditor) _emrEditor.refresh();
            Dom.toast('属性已更新', 'success');
        } },
        { text: '取消', type: 'default' }
    ]);
    /* 测试交互 (下拉槽): 随选项/类型实时重建, 选择即预览拼接结果 */
    const optsTa = sub.querySelector('#prop-opts');
    if (optsTa) {
        const renderTest = function () {
            const lines = (sub.querySelector('#prop-opts').value || '').split('\n').map(function (x) { return x.trim(); }).filter(Boolean);
            const sel = sub.querySelector('input[name="sel-type"]:checked');
            const multi = sel && sel.value === 'multi';
            const box = sub.querySelector('#prop-test');
            const prev = sub.querySelector('#prop-test-val');
            prev.textContent = '—';
            if (!lines.length) { box.innerHTML = '<span class="emr-prop-testempty">暂无选项</span>'; return; }
            box.innerHTML = lines.map(function (o2) {
                return '<label class="emr-prop-radio"><input type="' + (multi ? 'checkbox' : 'radio') + '" name="prop-testpick" value="' + Format.escape(o2) + '"> ' + Format.escape(o2) + '</label>';
            }).join('');
            box.querySelectorAll('input').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    const vals = Array.from(box.querySelectorAll('input:checked')).map(function (x) { return x.value; });
                    prev.textContent = vals.join('、') || '—';
                });
            });
        };
        optsTa.addEventListener('input', renderTest);
        sub.querySelectorAll('input[name="sel-type"]').forEach(function (r) { r.addEventListener('change', renderTest); });
        renderTest();
    }
}

/** 设计工具: 插入字段 / 下拉字段(单选/复选) / 标题 / 引用 */
function emr_dsnInsertField(withSelect, multi) {
    if (!_emrEditor) return;
    const title = withSelect ? (multi ? '插入复选框字段' : '插入下拉字段') : '插入字段';
    const content = '<div class="form-group"><label class="form-label">字段名称（显示为固定标签）</label><input id="dsn-label" class="form-control" placeholder="如：职业史"></div>'
        + (withSelect ? '<div class="form-group"><label class="form-label">选项（用顿号/逗号分隔，可留空稍后右击配置）</label><input id="dsn-opts" class="form-control" placeholder="如：无、有、不详"></div>'
            + (multi ? '<p class="emr-tpl-hint">复选：书写时可勾选多项，自动以顿号拼接（如 1、2）</p>' : '') : '');
    Dom.modal(title, content, [
        { text: '插入', type: 'primary', onclick: function () {
            const label = (document.getElementById('dsn-label').value || '').trim();
            if (!label) { Dom.toast('请输入字段名称', 'error'); return; }
            const select = withSelect ? (document.getElementById('dsn-opts').value || '').split(/[、，,;；]/).map(function (x) { return x.trim(); }).filter(Boolean) : [];
            _emrEditor.insertField(label, withSelect ? { select: select, multi: !!multi } : null);
        } },
        { text: '取消', type: 'default' }
    ]);
}
function emr_dsnInsertHeading() {
    if (!_emrEditor) return;
    const content = '<div class="form-group"><label class="form-label">标题文字</label><input id="dsn-h" class="form-control" placeholder="如：体格检查"></div>';
    Dom.modal('插入标题', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const t = (document.getElementById('dsn-h').value || '').trim();
            if (!t) { Dom.toast('请输入标题文字', 'error'); return; }
            _emrEditor.insertHeading(t);
        } },
        { text: '取消', type: 'default' }
    ]);
}
function emr_dsnInsertRef() {
    if (!_emrEditor) return;
    const content = '<div class="form-group"><label class="form-label">字段键（其他文书的字段标识）</label>'
        + '<input id="dsn-ref" class="form-control" list="dsn-ref-list" placeholder="如：chief_complaint_val（主诉）">'
        + '<datalist id="dsn-ref-list"><option value="p_name">姓名</option><option value="p_age">年龄</option><option value="p_gender">性别</option><option value="chief_complaint_val">主诉</option><option value="present_illness_val">现病史</option><option value="past_history_val">既往史</option><option value="special_exam_val">专科检查</option><option value="aux_exam_val">辅助检查</option><option value="vital_bmi_value">BMI</option></datalist></div>'
        + '<div class="form-group"><label class="form-label">无值时的占位文字</label><input id="dsn-ref-fb" class="form-control" value="＿＿"></div>';
    Dom.modal('插入字段引用', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const k = (document.getElementById('dsn-ref').value || '').trim();
            const fb = (document.getElementById('dsn-ref-fb').value || '＿＿').trim();
            if (!k) { Dom.toast('请输入字段键', 'error'); return; }
            _emrEditor.insertRef(k, fb);
        } },
        { text: '取消', type: 'default' }
    ]);
}

/** 科室显示名: 全院通用(NULL) / 实时科室名 (id 关联, 随管理员更名同步) / 已删除科室兜底 */
function emrDeptNameOf(t) {
    if (t.department_id == null) return '全院通用';
    return t.department_name ? t.department_name : ('科室#' + t.department_id + '（已弃用）');
}

/** 模板管理弹窗: 本科室=左分类/右列表 两栏; 全院=左科室/中分类/右列表 三栏
 *  本科室可设计/删除, 全院可复制到本科室; preCatId 可预选分类 */
async function emr_openTemplateManager(preCatId) {
    const [r, cr] = await Promise.all([
        AjaxLoader.api('emr/template-list?structured=1', { silent: true }),
        AjaxLoader.api('emr/category-list', { silent: true })
    ]);
    let templates = r.data || [];
    const cats = cr.data || [];
    if (!templates.length && !cats.length) { Dom.toast('数据加载失败', 'error'); return; }
    const myDeptId = Auth.user && Auth.user.department_id ? String(Auth.user.department_id) : null;
    let activeTab = 'mine';
    let activeCat = (typeof preCatId === 'string' || typeof preCatId === 'number') ? String(preCatId) : 'all';
    let activeDept = '0'; /* 全院 Tab: '0'=全院通用, 其余=科室id (id 关联, 名称实时同步) */

    const isOwn = (t) => t.department_id != null && String(t.department_id) === myDeptId;
    const deptKeyOf = (t) => t.department_id == null ? '0' : String(t.department_id);
    const tabTpls = () => activeTab === 'mine'
        ? templates.filter(function (t) { return isOwn(t); })
        : templates.filter(function (t) { return deptKeyOf(t) === activeDept; });
    const catKeyOf = (t) => t.category_id == null ? 'none' : String(t.category_id);
    const catTpls = (key) => key === 'all'
        ? tabTpls()
        : tabTpls().filter(function (t) { return catKeyOf(t) === key; });

    function actHTML(t) {
        if (isOwn(t)) return '<span class="mini" data-a="design">设计</span><span class="mini danger" data-a="del">删除</span>';
        return '<span class="mini" data-a="clone">复制到本科室</span>';
    }

    const content = '<div class="emr-tpm">'
        + '<div class="emr-tpl-tabs"><span class="emr-tpl-tab active" data-tab="mine">本科室</span><span class="emr-tpl-tab" data-tab="all">全院</span></div>'
        + '<div class="emr-tpm-body">'
        + '<div class="emr-tpm-depts" id="emr-tpm-depts" style="display:none"></div>'
        + '<div class="emr-tpm-cats" id="emr-tpm-cats"></div>'
        + '<div class="emr-tpm-main" id="emr-tpm-main"></div>'
        + '</div>'
        + '<div class="emr-tpl-foot"><button type="button" class="btn btn-small btn-primary" id="emr-tpm-new">新建模板</button><span class="emr-tb-spacer"></span><button type="button" class="btn" id="emr-tpm-close">关闭</button></div>'
        + '</div>';
    const overlay = Dom.modal('病历模板管理', content, []);
    overlay.querySelector('.modal').classList.add('modal-lg');
    const deptsEl = overlay.querySelector('#emr-tpm-depts');
    const catsEl = overlay.querySelector('#emr-tpm-cats');
    const mainEl = overlay.querySelector('#emr-tpm-main');

    async function reload() {
        const rr = await AjaxLoader.api('emr/template-list?structured=1', { silent: true });
        templates = rr.data || [];
    }

    /** 全院 Tab 左栏: 科室列表 (全院通用/各科室/已弃用科室兜底; 计数=该科室模板总数) */
    function renderDepts() {
        const map = new Map();
        templates.forEach(function (t) {
            const k = deptKeyOf(t);
            if (!map.has(k)) map.set(k, { key: k, name: emrDeptNameOf(t), n: 0 });
            map.get(k).n++;
        });
        const list = Array.from(map.values()).sort(function (a, b) {
            if (a.key === '0') return -1;
            if (b.key === '0') return 1;
            if ((a.key === myDeptId) !== (b.key === myDeptId)) return a.key === myDeptId ? -1 : 1;
            return a.name.localeCompare(b.name, 'zh');
        });
        if (!list.some(function (x) { return x.key === activeDept; })) activeDept = '0';
        deptsEl.innerHTML = list.map(function (x) {
            const tag = x.key === myDeptId ? '<span class="emr-tpm-dtag">本科室</span>' : '';
            return '<div class="emr-tpm-cat' + (x.key === activeDept ? ' active' : '') + '" data-dept="' + x.key + '">'
                + '<span class="emr-tpm-cat-name" title="' + Format.escape(x.name) + '">' + Format.escape(x.name) + '</span>'
                + tag + '<span class="emr-tpm-cat-count">' + x.n + '</span></div>';
        }).join('');
        deptsEl.querySelectorAll('[data-dept]').forEach(function (el) {
            el.addEventListener('click', function () {
                activeDept = el.dataset.dept;
                renderDepts();
                renderCats();
                renderMain();
            });
        });
    }

    /** 左栏: 分类列表 (全部 + 各分类 + 未分类), 计数随当前 Tab 变化 */
    function renderCats() {
        const items = [{ key: 'all', name: '全部' }].concat(cats.map(function (c) { return { key: String(c.id), name: c.name }; }));
        if (tabTpls().some(function (t) { return catKeyOf(t) === 'none'; })) items.push({ key: 'none', name: '未分类' });
        if (!items.some(function (x) { return x.key === activeCat; })) activeCat = 'all';
        catsEl.innerHTML = items.map(function (x) {
            return '<div class="emr-tpm-cat' + (x.key === activeCat ? ' active' : '') + '" data-cat="' + x.key + '">'
                + '<span class="emr-tpm-cat-name">' + Format.escape(x.name) + '</span>'
                + '<span class="emr-tpm-cat-count">' + catTpls(x.key).length + '</span></div>';
        }).join('');
        catsEl.querySelectorAll('.emr-tpm-cat').forEach(function (el) {
            el.addEventListener('click', function () {
                activeCat = el.dataset.cat;
                renderCats();
                renderMain();
            });
        });
    }

    /** 右栏: 当前分类下的模板行 (全院 Tab 头部标注当前科室, 行内不再重复) */
    function renderMain() {
        const list = catTpls(activeCat).slice().sort(function (a, b) { return String(a.name).localeCompare(String(b.name), 'zh'); });
        const catName = activeCat === 'all' ? '全部'
            : activeCat === 'none' ? '未分类'
            : ((cats.find(function (c) { return String(c.id) === activeCat; }) || {}).name || '模板');
        let deptName = '';
        if (activeTab === 'all') {
            const dt = templates.find(function (x) { return deptKeyOf(x) === activeDept; });
            deptName = dt ? emrDeptNameOf(dt) : '全院通用';
        }
        const head = '<div class="emr-tpm-mhead"><span class="emr-tpm-mtitle">' + Format.escape(catName) + '</span>'
            + (deptName ? '<span class="emr-tpm-mdept">· ' + Format.escape(deptName) + '</span>' : '')
            + '<span class="emr-tpm-mcount">' + list.length + ' 个模板</span></div>';
        let body;
        if (!list.length) {
            body = '<div class="emr-tpl-empty">' + (activeTab === 'mine'
                ? '该分类暂无本科室模板：可切换到「全院」将模板复制到本科室，或点击下方"新建模板"创建'
                : '「' + Format.escape(deptName) + '」下该分类暂无模板') + '</div>';
        } else {
            body = list.map(function (t) {
                return '<div class="emr-tpm-row" data-id="' + t.id + '">'
                    + '<span class="emr-tpm-rname" title="' + Format.escape(t.name) + '">' + Format.escape(t.name) + '</span>'
                    + '<span class="emr-tpm-ract">' + actHTML(t) + '</span></div>';
            }).join('');
        }
        mainEl.innerHTML = head + body;
        mainEl.querySelectorAll('.emr-tpm-row').forEach(function (row) {
            row.querySelectorAll('.mini').forEach(function (a) {
                a.addEventListener('click', async function () {
                    const t = templates.find(function (x) { return String(x.id) === String(row.dataset.id); });
                    if (!t) return;
                    if (a.dataset.a === 'design') { overlay.remove(); emr_enterTemplateDesign(t.id); return; }
                    if (a.dataset.a === 'del') {
                        Dom.modal('删除模板', '<p>确认删除模板「' + Format.escape(t.name) + '」？引用该模板的文书不受影响（内容独立保存）。</p>', [
                            { text: '删除', type: 'danger', onclick: async function () {
                                await AjaxLoader.api('emr/template-delete?id=' + t.id, { method: 'POST' });
                                Dom.toast('已删除', 'success');
                                await reload();
                                renderDepts();
                                renderCats();
                                renderMain();
                            } },
                            { text: '取消', type: 'default' }
                        ]);
                        return;
                    }
                    if (a.dataset.a === 'clone') {
                        const tr = await AjaxLoader.api('emr/template-get?id=' + t.id, { silent: true });
                        await AjaxLoader.api('emr/template-save', { method: 'POST', data: {
                            name: t.name + '（本科室副本）', record_type: t.record_type,
                            content_delta: '{}', content_html: '',
                            schema_json: tr.data ? tr.data.schema_json : '{}',
                            category_id: t.category_id
                        }});
                        Dom.toast('已复制到本科室', 'success');
                        await reload();
                        renderDepts();
                        renderCats();
                        renderMain();
                    }
                });
            });
        });
    }

    overlay.querySelectorAll('.emr-tpl-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            overlay.querySelectorAll('.emr-tpl-tab').forEach(function (x) { x.classList.remove('active'); });
            tab.classList.add('active');
            activeTab = tab.dataset.tab;
            deptsEl.style.display = activeTab === 'all' ? '' : 'none';
            renderDepts();
            renderCats();
            renderMain();
        });
    });
    renderDepts();
    renderCats();
    renderMain();
    overlay.querySelector('#emr-tpm-close').addEventListener('click', function () { overlay.remove(); });
    overlay.querySelector('#emr-tpm-new').addEventListener('click', function () {
        const defCat = cats.some(function (c) { return String(c.id) === activeCat; }) ? activeCat : (cats.length ? String(cats[0].id) : '');
        const catOpts = cats.map(function (c) { return '<option value="' + c.id + '"' + (String(c.id) === defCat ? ' selected' : '') + '>' + Format.escape(c.name) + '</option>'; }).join('');
        let selBase = ''; /* '' = 空白模板 (任何分类都提供) */
        function baseRows(catId) {
            const list = templates.filter(function (t) { return String(t.category_id) === String(catId); })
                .slice().sort(function (a, b) { return String(a.name).localeCompare(String(b.name), 'zh'); });
            let html = '<div class="emr-newtpl-base' + (selBase === '' ? ' active' : '') + '" data-id="">'
                + '<span class="emr-newtpl-base-name">空白模板（自由发挥）</span><span class="emr-newtpl-base-sub">从零开始</span></div>';
            html += list.map(function (t) {
                return '<div class="emr-newtpl-base' + (String(t.id) === String(selBase) ? ' active' : '') + '" data-id="' + t.id + '">'
                    + '<span class="emr-newtpl-base-name" title="' + Format.escape(t.name) + '">' + Format.escape(t.name) + '</span>'
                    + '<span class="emr-tpm-rdept">' + Format.escape(emrDeptNameOf(t)) + '</span></div>';
            }).join('');
            return html;
        }
        const sub = Dom.modal('新建模板',
            '<div class="emr-newtpl">'
            + '<div class="emr-newtpl-left">'
            + '<div class="form-group"><label class="form-label">模板名称</label><input id="emr-newtpl-name" class="form-control" placeholder="如：男性患者入院记录"></div>'
            + '<div class="form-group"><label class="form-label">所属分类</label><select id="emr-newtpl-cat" class="form-control">' + catOpts + '</select></div>'
            + '<p class="emr-tpl-hint">可选择右侧现有模板作为底稿（如"入院记录"），在设计模式中自由增删修改；也可选择空白模板从零发挥。</p>'
            + '</div>'
            + '<div class="emr-newtpl-right"><div class="emr-newtpl-rhead">基础模板</div>'
            + '<div class="emr-newtpl-bases" id="emr-newtpl-bases">' + baseRows(defCat) + '</div></div>'
            + '</div>',
            [
                { text: '创建并设计', type: 'primary', onclick: async function () {
                    const name = (document.getElementById('emr-newtpl-name').value || '').trim();
                    const cid = document.getElementById('emr-newtpl-cat').value;
                    if (!name) { Dom.toast('请输入模板名称', 'error'); return; }
                    const cat = cats.find(function (c) { return String(c.id) === String(cid); });
                    let schema = {
                        mode: 'word', title: name,
                        page: Object.assign({}, EmrDoc.DEFAULT_PAGE),
                        header: { hospital: '', doc_name: name, info_fields: EmrDoc.DEFAULT_INFO_FIELDS },
                        body: [
                            EmrDoc.para([EmrDoc.text(name, { b: true, sz: 14 })], { align: 'center' }),
                            EmrDoc.para([EmrDoc.fx('sign_line', '医师签名：'), EmrDoc.item('dyn', { key: 'sign_names', text: '' })], { align: 'right' })
                        ]
                    };
                    /* 以现有模板为底稿: 复制其版式/正文, 仅替换名称 (科室归属自动为本科室) */
                    if (selBase) {
                        try {
                            const tr = await AjaxLoader.api('emr/template-get?id=' + selBase, { silent: true });
                            const bs = parseSchema(tr.data && tr.data.schema_json);
                            if (bs && bs.mode === 'word') {
                                schema = {
                                    mode: 'word', title: name, page: bs.page,
                                    header: Object.assign({}, bs.header, { doc_name: name }),
                                    body: bs.body
                                };
                            }
                        } catch (e) { console.error('基础模板读取失败', e); }
                    }
                    const resp = await AjaxLoader.api('emr/template-save', { method: 'POST', data: {
                        name: name, record_type: (cat && cat.code) || 'admission',
                        content_delta: '{}', content_html: '',
                        schema_json: JSON.stringify(schema),
                        category_id: cat ? cat.id : null
                    }});
                    overlay.remove();
                    emr_enterTemplateDesign(resp.data.id);
                } },
                { text: '取消', type: 'default' }
            ]);
        /* 基础模板列表选择 (单选卡片) */
        const basesEl = sub.querySelector('#emr-newtpl-bases');
        function bindBases() {
            basesEl.querySelectorAll('.emr-newtpl-base').forEach(function (el) {
                el.addEventListener('click', function () {
                    selBase = el.dataset.id;
                    basesEl.querySelectorAll('.emr-newtpl-base').forEach(function (x) { x.classList.remove('active'); });
                    el.classList.add('active');
                });
            });
        }
        bindBases();
        /* 分类联动基础模板列表 */
        const catSel = sub.querySelector('#emr-newtpl-cat');
        catSel.addEventListener('change', function () {
            selBase = '';
            basesEl.innerHTML = baseRows(catSel.value);
            bindBases();
        });
    });
}

/** 内容库管理 (纯文本条目): 左右两栏 (左分类/右条目), 新建与编辑在模态框内完成 */
async function emr_openContentManager(preCatId) {
    const [r, cr] = await Promise.all([
        AjaxLoader.api('emr/content-list', { silent: true }),
        AjaxLoader.api('emr/category-list', { silent: true })
    ]);
    let items = r.data || [];
    const cats = cr.data || [];
    let activeCat = (typeof preCatId === 'string' || typeof preCatId === 'number') ? String(preCatId) : 'all';

    const catKeyOf = (t) => t.category_id == null ? 'none' : String(t.category_id);
    const listOf = (key) => key === 'all' ? items : items.filter(function (t) { return catKeyOf(t) === key; });

    const content = '<div class="emr-tpm">'
        + '<div class="emr-tpm-body">'
        + '<div class="emr-tpm-cats" id="emr-cm-cats"></div>'
        + '<div class="emr-tpm-main" id="emr-cm-main"></div>'
        + '</div>'
        + '<div class="emr-tpl-foot"><button type="button" class="btn btn-small btn-primary" id="emr-cm-new">新建内容</button><span class="emr-tb-spacer"></span><button type="button" class="btn" id="emr-cm-close">关闭</button></div>'
        + '</div>';
    const overlay = Dom.modal('病历内容库', content, []);
    overlay.querySelector('.modal').classList.add('modal-lg');
    const catsEl = overlay.querySelector('#emr-cm-cats');
    const mainEl = overlay.querySelector('#emr-cm-main');

    async function reload() {
        const rr = await AjaxLoader.api('emr/content-list', { silent: true });
        items = rr.data || [];
    }

    function renderCats() {
        const list = [{ key: 'all', name: '全部' }].concat(cats.map(function (c) { return { key: String(c.id), name: c.name }; }));
        if (items.some(function (t) { return catKeyOf(t) === 'none'; })) list.push({ key: 'none', name: '未分类' });
        if (!list.some(function (x) { return x.key === activeCat; })) activeCat = 'all';
        catsEl.innerHTML = list.map(function (x) {
            return '<div class="emr-tpm-cat' + (x.key === activeCat ? ' active' : '') + '" data-cat="' + x.key + '">'
                + '<span class="emr-tpm-cat-name">' + Format.escape(x.name) + '</span>'
                + '<span class="emr-tpm-cat-count">' + listOf(x.key).length + '</span></div>';
        }).join('');
        catsEl.querySelectorAll('.emr-tpm-cat').forEach(function (el) {
            el.addEventListener('click', function () { activeCat = el.dataset.cat; renderCats(); renderMain(); });
        });
    }

    function renderMain() {
        const list = listOf(activeCat);
        const catName = activeCat === 'all' ? '全部' : activeCat === 'none' ? '未分类'
            : ((cats.find(function (c) { return String(c.id) === activeCat; }) || {}).name || '内容');
        const head = '<div class="emr-tpm-mhead"><span class="emr-tpm-mtitle">' + Format.escape(catName) + '</span><span class="emr-tpm-mcount">' + list.length + ' 条内容</span></div>';
        let body;
        if (!list.length) {
            body = '<div class="emr-tpl-empty">该分类暂无内容：点击下方"新建内容"创建（纯文本片段，写病历时可整段或选段写入）</div>';
        } else {
            body = list.map(function (t) {
                const tag = t.category_name ? '<span class="emr-tpm-rdept">' + Format.escape(t.category_name) + '</span>' : '';
                return '<div class="emr-tpm-row" data-id="' + t.id + '">'
                    + '<span class="emr-tpm-rname" title="' + Format.escape(t.name) + '">' + Format.escape(t.name) + '</span>'
                    + tag + '<span class="emr-tpm-ract"><span class="mini" data-a="edit">编辑</span><span class="mini danger" data-a="del">删除</span></span></div>';
            }).join('');
        }
        mainEl.innerHTML = head + body;
        mainEl.querySelectorAll('.emr-tpm-row').forEach(function (row) {
            row.querySelectorAll('.mini').forEach(function (a) {
                a.addEventListener('click', function () {
                    const t = items.find(function (x) { return String(x.id) === String(row.dataset.id); });
                    if (!t) return;
                    if (a.dataset.a === 'edit') { emr_contentEditDialog(t, cats, reload, function () { renderCats(); renderMain(); }); return; }
                    Dom.modal('删除内容', '<p>确认删除内容「' + Format.escape(t.name) + '」？</p>', [
                        { text: '删除', type: 'danger', onclick: async function () {
                            await AjaxLoader.api('emr/content-delete?id=' + t.id, { method: 'POST' });
                            Dom.toast('已删除', 'success');
                            await reload();
                            renderCats();
                            renderMain();
                        } },
                        { text: '取消', type: 'default' }
                    ]);
                });
            });
        });
    }

    overlay.querySelector('#emr-cm-close').addEventListener('click', function () { overlay.remove(); });
    overlay.querySelector('#emr-cm-new').addEventListener('click', function () {
        const defCat = cats.some(function (c) { return String(c.id) === activeCat; }) ? activeCat : (cats.length ? String(cats[0].id) : '');
        emr_contentEditDialog({ id: null, name: '', category_id: defCat, content: '' }, cats, reload, function () { renderCats(); renderMain(); });
    });
    renderCats();
    renderMain();
}

/** 内容条目编辑对话框 (标题/分类/正文); done 回调刷新列表 */
function emr_contentEditDialog(item, cats, reload, done) {
    const catOpts = cats.map(function (c) {
        return '<option value="' + c.id + '"' + (String(c.id) === String(item.category_id) ? ' selected' : '') + '>' + Format.escape(c.name) + '</option>';
    }).join('');
    Dom.modal(item.id ? '编辑内容' : '新建内容',
        '<div class="form-group"><label class="form-label">内容标题</label><input id="cm-name" class="form-control" placeholder="如：锁骨骨折查体" value="' + Format.escape(item.name || '') + '"></div>'
        + '<div class="form-group"><label class="form-label">所属分类</label><select id="cm-cat" class="form-control">' + catOpts + '</select></div>'
        + '<div class="form-group"><label class="form-label">内容正文（纯文本）</label>'
        + '<textarea id="cm-content" class="form-control" rows="8" placeholder="输入可供引用的文字内容…">' + Format.escape(item.content || '') + '</textarea></div>',
        [
            { text: '保存', type: 'primary', onclick: async function () {
                const name = (document.getElementById('cm-name').value || '').trim();
                if (!name) { Dom.toast('请输入内容标题', 'error'); return; }
                const data = {
                    name: name,
                    category_id: document.getElementById('cm-cat').value,
                    content: document.getElementById('cm-content').value || ''
                };
                if (item.id) data.id = item.id;
                await AjaxLoader.api('emr/content-save', { method: 'POST', data: data });
                Dom.toast('已保存', 'success');
                await reload();
                if (done) done();
            } },
            { text: '取消', type: 'default' }
        ]);
}

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

/** 一键同步诊断: 按 dyn key 取左侧「诊断」模块对应分类拼为文本 (diag_discharge→出院诊断, 其余→入院诊断) */
async function fetchAdmissionDiagText(dynKey) {
    const cat = dynKey === 'diag_discharge' ? 'discharge' : 'admission';
    const r = await AjaxLoader.api('emr/diagnosis-list?patient_id=' + _emrPatient.id, { silent: true });
    const rows = (r.data || []).filter(x => x.category === cat);
    if (!rows.length) {
        if (cat === 'admission' && _emrPatient && _emrPatient.admission_diagnosis) return String(_emrPatient.admission_diagnosis);
        Dom.toast('诊断中暂无' + (cat === 'discharge' ? '出院诊断' : '入院诊断') + '，请先在左侧「诊断」中添加', 'error');
        return null;
    }
    return rows.map((x, i) => rows.length > 1 ? (i + 1) + '.' + x.name : x.name).join('；');
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
     'btn-undo', 'btn-redo', 'btn-save', 'btn-template'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = signed;
    });
    document.getElementById('btn-sign').disabled = signed;
    document.getElementById('btn-print').disabled = false;
    document.getElementById('btn-versions').disabled = false;
    const statusEl = document.getElementById('emr-doc-status');
    statusEl.textContent = signed ? '已签名 · 只读锁定' : '草稿 · 可编辑';
    statusEl.className = 'emr-doc-status ' + (signed ? 'signed' : 'draft');
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
        setSaveState('已保存 ' + emrNowStamp().slice(11), 'saved');
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

/* ================= 引用面板 (状态栏 ↑): 病历/检验/检查/医嘱/既往/内容 → 写回 ================= */
var _emrRefCaret = null;   // 打开面板时的光标位置 (写回定位用)
var _emrRefTab = 'doc';
var _emrRefCache = {};     // 文书 id → 纯文本缓存

function emr_refToggle() {
    const panel = document.getElementById('emr-ref-panel');
    if (panel.style.display !== 'none') { panel.style.display = 'none'; return; }
    if (_emrDesign) { Dom.toast('模板设计中不可使用引用写回', 'info'); return; }
    if (!_emrEditor || _emrEditor.readonly) { Dom.toast('请先打开可编辑的文书', 'info'); return; }
    _emrRefCaret = _emrEditor.getCaretPoint() || _emrEditor.getDocEndPoint(); /* 无光标时追加到文末 */
    panel.style.display = 'flex';
    emr_refSwitch(_emrRefTab);
}

function emr_refSwitch(tab) {
    _emrRefTab = tab;
    document.querySelectorAll('.emr-refp-tab').forEach(function (t) { t.classList.toggle('active', t.dataset.tab === tab); });
    document.getElementById('emr-refp-left').innerHTML = '<div class="emr-refp-empty">加载中…</div>';
    document.getElementById('emr-refp-right').innerHTML = '<div class="emr-refp-empty">从左侧选择一条记录，右侧显示内容；可全选或选中部分文字后点击「写回病历」</div>';
    const loader = { doc: emr_refLoadDocs, past: emr_refLoadDocs, lab: emr_refLoadLab, exam: emr_refLoadExam, order: emr_refLoadOrders, content: emr_refLoadContent }[tab];
    if (loader) loader();
}

/** 左侧列表渲染: items = [{name, time, load()}] */
function emr_refListRender(items) {
    const left = document.getElementById('emr-refp-left');
    if (!items.length) {
        left.innerHTML = '<div class="emr-refp-empty">' + (_emrRefTab === 'past' ? '暂无既往住院病历' : '暂无可引用记录') + '</div>';
        return;
    }
    left.innerHTML = items.map(function (x, i) {
        return '<div class="emr-refp-item" data-i="' + i + '"><div class="emr-refp-name">' + Format.escape(x.name) + '</div><div class="emr-refp-time">' + Format.escape(x.time || '') + '</div></div>';
    }).join('');
    left.querySelectorAll('.emr-refp-item').forEach(function (el) {
        el.addEventListener('click', async function () {
            left.querySelectorAll('.emr-refp-item').forEach(function (x) { x.classList.remove('active'); });
            el.classList.add('active');
            const x = items[+el.dataset.i];
            const right = document.getElementById('emr-refp-right');
            right.innerHTML = '<div class="emr-refp-empty">加载中…</div>';
            try { right.textContent = await x.load(); } catch (e) { right.innerHTML = '<div class="emr-refp-empty">加载失败</div>'; }
        });
    });
}

/** 文书 doc_json → 纯文本 (含表格行) */
function emr_refDocText(doc) {
    const blockLines = function (b) {
        if (b.t === 'p') {
            let s = '';
            for (const it of (b.runs || [])) {
                if (it.t === 'text') s += it.text;
                else if (it.t === 'fx') s += it.text;
                else if (it.t === 'dyn' || it.t === 'ref') s += (it.text || '');
                else if (it.t === 'tab') s += '\t';
            }
            return [s];
        }
        if (b.t === 'tbl') {
            const out = [];
            for (const r of (b.rows || [])) {
                const cells = (r.cells || []).map(function (c) {
                    let t = '';
                    (c.blocks || []).forEach(function (cb) { t += blockLines(cb).join(' '); });
                    return t.trim();
                });
                out.push(cells.join(' | '));
            }
            return out;
        }
        return [];
    };
    const lines = [];
    (doc.body || []).forEach(function (b) { lines.push.apply(lines, blockLines(b)); });
    return lines.join('\n').replace(/\n{3,}/g, '\n\n').trim();
}

async function emr_refLoadDocs() {
    const r = await AjaxLoader.api('emr/document-list?patient_id=' + _emrPatient.id, { silent: true });
    let docs = r.data || [];
    if (_emrRefTab === 'past') {
        docs = docs.filter(function (d) { return d.admission_no && _emrPatient.admission_no && d.admission_no !== _emrPatient.admission_no; });
    }
    emr_refListRender(docs.map(function (d) {
        return {
            name: (d.title || d.template_name || '未命名文书') + (d.status === 'signed' ? '（已签名）' : ''),
            time: emrStamp(d.updated_at || d.created_at || ''),
            load: async function () {
                if (_emrRefCache[d.id] != null) return _emrRefCache[d.id];
                const dr = await AjaxLoader.api('emr/document-get?id=' + d.id, { silent: true });
                const txt = emr_refDocText((dr.data && dr.data.doc_json) || {});
                _emrRefCache[d.id] = txt;
                return txt || '（空文书）';
            }
        };
    }));
}

async function emr_refLoadLab() {
    const r = await AjaxLoader.api('lab/list?patient_id=' + _emrPatient.id, { silent: true });
    emr_refListRender((r.data || []).map(function (x) {
        return {
            name: x.exam_name || '检验报告',
            time: emrStamp(x.reported_at || x.created_at || ''),
            load: async function () {
                const dr = await AjaxLoader.api('lab/detail?id=' + x.id, { silent: true });
                const d = dr.data || {};
                const lines = (d.items || []).map(function (it) {
                    return it.item_name + '：' + (it.result || '') + (it.unit ? ' ' + it.unit : '') + (it.reference_range ? '（参考 ' + it.reference_range + '）' : '') + (it.flag ? ' ' + it.flag : '');
                });
                return '【' + (d.exam_name || '') + '】' + (d.reported_at ? ' 报告时间 ' + d.reported_at : '') + '\n' + (lines.join('\n') || '（暂无结果明细）');
            }
        };
    }));
}

async function emr_refLoadExam() {
    const r = await AjaxLoader.api('exam/list?patient_id=' + _emrPatient.id, { silent: true });
    emr_refListRender((r.data || []).map(function (x) {
        return {
            name: (x.exam_type || '检查') + (x.body_part ? '·' + x.body_part : ''),
            time: emrStamp(x.reported_at || x.created_at || ''),
            load: async function () {
                return '【' + (x.exam_type || '') + (x.body_part ? '（' + x.body_part + '）' : '') + '】' + (x.reported_at ? ' 报告时间 ' + x.reported_at : '')
                    + (x.findings ? '\n检查所见：' + x.findings : '')
                    + (x.conclusion ? '\n检查结论：' + x.conclusion : '')
                    + (x.impression ? '\n印象：' + x.impression : '')
                    + (!x.findings && !x.conclusion && !x.impression ? '\n（暂无报告内容）' : '');
            }
        };
    }));
}

async function emr_refLoadOrders() {
    const r = await AjaxLoader.api('orders/list?patient_id=' + _emrPatient.id, { silent: true });
    emr_refListRender((r.data || []).map(function (x) {
        return {
            name: (x.content || '医嘱') + (x.dosage ? ' ' + x.dosage : '') + (x.frequency ? ' ' + x.frequency : ''),
            time: emrStamp(x.start_date || x.created_at || '') + (x.status ? ' · ' + x.status : ''),
            load: async function () {
                return (x.order_period === 'temp' ? '临时医嘱' : '长期医嘱') + '：' + (x.content || '')
                    + (x.dosage ? '\n剂量：' + x.dosage + (x.dosage_unit || '') : '')
                    + (x.frequency ? '\n频次：' + x.frequency : '')
                    + (x.usage_name ? '\n用法：' + x.usage_name : '')
                    + (x.notes ? '\n备注：' + x.notes : '')
                    + (x.start_date ? '\n开始：' + x.start_date : '');
            }
        };
    }));
}

async function emr_refLoadContent() {
    const r = await AjaxLoader.api('emr/content-list', { silent: true });
    emr_refListRender((r.data || []).map(function (x) {
        return {
            name: (x.category_name ? '[' + x.category_name + '] ' : '') + x.name,
            time: emrStamp(x.updated_at || ''),
            load: async function () { return x.content || '（空内容）'; }
        };
    }));
}

/** 写回病历: 右侧选中部分文字则写所选, 否则写全部; 按行分段写入光标处 */
function emr_refWriteBack() {
    const right = document.getElementById('emr-refp-right');
    let text = '';
    const sel = window.getSelection();
    if (sel && sel.rangeCount && right.contains(sel.anchorNode)) {
        const t = String(sel.toString() || '').trim();
        if (t) text = t;
    }
    if (!text) text = String(right.innerText || '').trim();
    if (!text) { Dom.toast('右侧暂无内容可写回', 'error'); return; }
    if (!_emrEditor) { Dom.toast('编辑器未就绪', 'error'); return; }
    _emrEditor.focusCaret(_emrRefCaret);
    const lines = text.replace(/\r/g, '').split('\n');
    lines.forEach(function (ln, i) {
        if (i > 0) _emrEditor.enter();
        if (ln) _emrEditor.insertText(ln);
    });
    Dom.toast('已写回病历', 'success');
}

/* ================= 签名 / 打印 / 版本 / 模板 ================= */
/** 必选字段未完成清单: {name, ph} —— 签名前校验 */
function emrFindMissingRequired() {
    if (!_emrEditor) return [];
    const missing = [];
    EmrOps.walkSlots(_emrEditor.getDoc().body, function (p, slot) {
        if (!slot.req) return;
        if (EmrOps.valueAfterSlot(p, slot.key).trim()) return;
        missing.push(slot.name || slot.ph || slot.key);
    });
    return missing;
}

function emr_signDoc() {
    if (!_emrCurrentDoc || _emrCurrentDoc.status === 'signed') return;
    const missing = emrFindMissingRequired();
    if (missing.length) {
        Dom.modal('无法签名：存在必选字段未完成',
            '<div class="emr-qc-list">' + missing.map(function (n) {
                return '<div class="emr-qc-item"><span class="emr-qc-tag" style="background:#fdecec;color:#e5484d">必选</span><span>' + Format.escape(n) + '</span></div>';
            }).join('') + '</div>'
            + '<p class="emr-qc-hint">请在文书中完成上述必选字段（红色下划线标记）后再签名。</p>',
            [{ text: '知道了', type: 'primary' }]);
        return;
    }
    const content = '<div class="form-group"><label class="form-label">请输入密码/工号确认身份</label><input type="password" id="sign-password" class="form-control"></div>';
    Dom.modal('医师签名', content, [
        { text: '确认签名', type: 'primary', onclick: function() {
            const pwd = document.getElementById('sign-password').value;
            if (!pwd) { Dom.toast('请输入密码', 'error'); return; }
            AjaxLoader.api('emr/document-sign', { method: 'POST', data: { id: _emrCurrentDoc.id, password: pwd } }).then(() => {
                Dom.toast('签名成功', 'success');
                emr_openDocument(_emrCurrentDoc.id);
                refreshDocs();
            });
        } },
        { text: '取消', type: 'default' }
    ]);
}

function emr_printDoc() {
    if (!_emrCurrentDoc) return;
    if (_emrCurrentDoc.status === 'signed' && _emrCurrentDoc.content_html) {
        PrintHelper.printDocument(_emrCurrentDoc.content_html); // 签名固化快照
        return;
    }
    if (_emrEditor) _emrEditor.print();
}

function emr_saveAsTemplate() {
    if (!_emrEditor) { Dom.toast('请先打开文书', 'error'); return; }
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

/* ================= 撤销/重做 ================= */
function emr_undo() { if (_emrEditor) _emrEditor.undo(); }
function emr_redo() { if (_emrEditor) _emrEditor.redo(); }

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
                refreshDocs();
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
    bind('btn-save', () => emr_saveDraft(false));
    bind('btn-print', emr_printDoc);
    bind('btn-template', emr_saveAsTemplate);
    bind('btn-versions', emr_showVersions);
    bind('btn-sign', emr_signDoc);
    bind('btn-template-mgr', () => emr_openTemplateManager());
    bind('btn-content-mgr', () => emr_openContentManager());
    bind('btn-ref-panel', emr_refToggle);
    bind('emr-refp-close', function () { document.getElementById('emr-ref-panel').style.display = 'none'; });
    bind('emr-refp-write', emr_refWriteBack);
    document.querySelectorAll('.emr-refp-tab').forEach(function (t) {
        t.addEventListener('click', function () { emr_refSwitch(t.dataset.tab); });
    });
    bind('dsn-sec-field', function () { emr_dsnToggleFields(); });
    bind('dsn-sec-fixed', function () { emr_dsnToggleFixed(); });
    bind('dsn-sec-select', function () {
        const head = document.getElementById('dsn-sec-select');
        const open = head.classList.toggle('open');
        document.getElementById('dsn-select').style.display = open ? '' : 'none';
    });
    bind('dsn-sec-diag', function () {
        const head = document.getElementById('dsn-sec-diag');
        const open = head.classList.toggle('open');
        document.getElementById('dsn-diag').style.display = open ? '' : 'none';
    });
    bind('dsn-sec-table', function () {
        const head = document.getElementById('dsn-sec-table');
        const open = head.classList.toggle('open');
        document.getElementById('dsn-table').style.display = open ? '' : 'none';
        if (open) emr_dsnBuildTablePicker();
    });
    (function () {
        const box = document.getElementById('dsn-diag');
        box.querySelectorAll('.emr-dsn-field').forEach(function (el) {
            el.addEventListener('click', function () {
                if (!_emrEditor) return;
                _emrEditor.insertItem({ t: 'dyn', key: el.dataset.diag === 'discharge' ? 'diag_discharge' : 'diag_admission', text: '' });
            });
        });
    })();
    (function () {
        const box = document.getElementById('dsn-select');
        box.querySelectorAll('.emr-dsn-field').forEach(function (el) {
            el.addEventListener('click', function () { emr_dsnInsertField(true, el.dataset.sel === 'multi'); });
        });
    })();
    bind('btn-dsn-select', function () { emr_dsnInsertField(true); });
    bind('btn-dsn-heading', emr_dsnInsertHeading);
    bind('btn-dsn-ref', emr_dsnInsertRef);
    bind('btn-dsn-save', emr_saveTemplateDesign);
    bind('btn-dsn-exit', function () {
        if (_emrEditor && _emrEditor.isDirty && _emrEditor.isDirty()) {
            Dom.modal('退出模板设计', '<p>存在未保存的修改，确定退出吗？</p>', [
                { text: '退出', type: 'danger', onclick: emr_exitTemplateDesign },
                { text: '取消', type: 'default' }
            ]);
            return;
        }
        emr_exitTemplateDesign();
    });
}

bindToolbar();

window.addEventListener('beforeunload', function(e) {
    if (_emrEditor && _emrEditor.isDirty()) { e.preventDefault(); e.returnValue = ''; }
});
</script>
