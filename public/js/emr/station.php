<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree">
        <div class="emr-tree-header">病历文书</div>
        <div id="emr-category-tree" class="emr-category-tree"></div>
        <div class="emr-tree-foot">
            <button type="button" class="btn btn-small" id="btn-template-mgr">模板</button>
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
                <button type="button" class="btn btn-small" id="btn-dsn-field">插入字段</button>
                <button type="button" class="btn btn-small" id="btn-dsn-select">插入下拉字段</button>
                <button type="button" class="btn btn-small" id="btn-dsn-heading">插入标题</button>
                <button type="button" class="btn btn-small" id="btn-dsn-ref">插入引用</button>
                <span class="emr-tb-spacer"></span>
                <button type="button" class="btn btn-small btn-success" id="btn-dsn-save">保存模板</button>
                <button type="button" class="btn btn-small" id="btn-dsn-exit">退出设计</button>
            </div>
            <div id="emr-editor-scroll" class="emr-editor-scroll">
                <div class="emr-editor-page" id="emr-editor-page">
                    <div id="emr-editor-canvas"></div>
                </div>
            </div>
            <div id="emr-editor-status" class="emr-statusbar"><span id="emr-doc-status" class="emr-doc-status"></span><span id="emr-sb-savetag" class="emr-sb-saved">已保存</span><span id="emr-save-state" class="emr-sb-state"></span><span id="emr-sb-count" class="emr-sb-count">字数 0</span></div>
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
    document.getElementById('emr-toolbar').style.display = 'none';
    document.getElementById('emr-design-bar').style.display = 'flex';
    document.getElementById('emr-design-name').textContent = '模板设计：' + _emrDesign.name;
    const dsEl = document.getElementById('emr-doc-status');
    dsEl.textContent = ''; dsEl.className = 'emr-doc-status';
    applyPageCss(schema.page);
    EmrOps.setDesignMode(true);
    _emrEditor = EmrEditor.create({
        container: document.getElementById('emr-editor-canvas'),
        doc: EmrDoc.fromSchema(schema),
        guard: null,
        patientInfo: {}, /* 设计模板与患者无关: 页眉/引用以占位符呈现 (render 层 design 标志) */
        design: true,
        onChange: null,
        onStatus: updateEditorStatus
    });
}

function emr_exitTemplateDesign() {
    EmrOps.setDesignMode(false);
    _emrDesign = null;
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

/** 设计工具: 插入字段 / 下拉字段 / 标题 / 引用 */
function emr_dsnInsertField(withSelect) {
    if (!_emrEditor) return;
    const content = '<div class="form-group"><label class="form-label">字段名称（显示为固定标签）</label><input id="dsn-label" class="form-control" placeholder="如：职业史"></div>'
        + (withSelect ? '<div class="form-group"><label class="form-label">下拉选项（用顿号/逗号分隔，可留空）</label><input id="dsn-opts" class="form-control" placeholder="如：无、有、不详"></div>' : '');
    Dom.modal(withSelect ? '插入下拉字段' : '插入字段', content, [
        { text: '插入', type: 'primary', onclick: function () {
            const label = (document.getElementById('dsn-label').value || '').trim();
            if (!label) { Dom.toast('请输入字段名称', 'error'); return; }
            const select = withSelect ? (document.getElementById('dsn-opts').value || '').split(/[、，,;；]/).map(function (x) { return x.trim(); }).filter(Boolean) : null;
            _emrEditor.insertField(label, select && select.length ? { select: select } : null);
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

/** 一键同步诊断: 取左侧「诊断」模块的入院诊断 (category=admission) 拼为文本; 无则提示 */
async function fetchAdmissionDiagText() {
    const r = await AjaxLoader.api('emr/diagnosis-list?patient_id=' + _emrPatient.id, { silent: true });
    const rows = (r.data || []).filter(x => x.category === 'admission');
    if (!rows.length) { Dom.toast('诊断中暂无入院诊断，请先在左侧「诊断」中添加', 'error'); return null; }
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
    bind('btn-dsn-field', function () { emr_dsnInsertField(false); });
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
