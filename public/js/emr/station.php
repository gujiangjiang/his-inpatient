<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="emr-station">
    <!-- 二级左栏: 病历分类树 + 文书目录 -->
    <div class="emr-tree">
        <div class="emr-tree-header">病历文书</div>
        <div id="emr-category-tree" class="emr-category-tree"></div>
        <div class="emr-tree-foot">
            <button type="button" class="btn btn-small btn-primary" id="btn-discharge">出院</button>
            <button type="button" class="btn btn-small" id="btn-template-mgr">模板</button>
        </div>
    </div>
    <!-- 二级主视图: 字处理病历编辑区 (类 Word 整页画布) -->
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
            <div id="emr-editor-scroll" class="emr-editor-scroll">
                <div class="emr-editor-page" id="emr-editor-page">
                    <div id="emr-editor-canvas"></div>
                </div>
            </div>
            <div id="emr-editor-status" class="emr-statusbar"><span class="emr-sb-saved">已保存</span><span id="emr-save-state"></span></div>
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
            item.innerHTML = '<span class="emr-doc-title-text">' + Format.escape(d.title || d.template_name || '未命名文书') + '</span>' +
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
        if (!groups.has(key)) groups.set(key, { name: t.department_name || '全院通用', items: [] });
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
        + '<div class="emr-tpl-opt"><label class="form-label">上级医师</label><select id="emr-tpl-senior" class="form-control" disabled><option value="">请选择</option></select></div>'
        + '<div class="emr-tpl-opt"><label class="form-label">职称</label><select id="emr-tpl-title" class="form-control" disabled><option value="">请选择</option><option>主任医师</option><option>副主任医师</option><option>主治医师</option><option>住院医师</option></select></div>'
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
const CASE_DYN = {
    p_name: (c, p) => c.name || (p && p.name) || '',
    p_gender: (c, p) => ({ '1': '男', '2': '女' }[c.gender]) || (p && p.gender === 'male' ? '男' : p && p.gender === 'female' ? '女' : (p && (p.gender === '男' || p.gender === '女') ? p.gender : '')),
    p_age: (c, p) => {
        if (c.age_y) return String(c.age_y);
        if (p && p.birth_date) { const a = new Date().getFullYear() - new Date(p.birth_date).getFullYear(); return a > 0 ? String(a) : ''; }
        return '';
    },
};
/* 可改字段 (slot): 未手动修改时自动跟随首页; 手动修改并保存后冻结 */
const CASE_SYNC = {
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

/* ================= 出院 (#6) ================= */
/** 解析 'YYYY-MM-DD HH:mm' 为 Date (兼容 Safari) */
function emrParseStamp(v) {
    if (!v) return null;
    const m = String(v).match(/(\d{4})-(\d{1,2})-(\d{1,2})[ T](\d{1,2}):(\d{1,2})/);
    if (!m) return null;
    const d = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], 0);
    return isNaN(d.getTime()) ? null : d;
}

/** 出院模态框: 今日(默认+30分钟)/明日(默认明早8点) + 日期时间选择 + 前后端双重校验 */
function emr_openDischarge() {
    const pad = n => String(n).padStart(2, '0');
    const fmt = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    const now = new Date();
    const defToday = new Date(now.getTime() + 30 * 60000);
    const defTomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 8, 0, 0);
    const content = '<div class="emr-dc">'
        + '<div class="emr-dc-modes">'
        + '<label class="emr-dc-mode"><input type="radio" name="emr-dc-mode" value="today" checked> 今日出院 <span class="emr-dc-mode-sub">（默认 30 分钟后；不自动停止医嘱）</span></label>'
        + '<label class="emr-dc-mode"><input type="radio" name="emr-dc-mode" value="tomorrow"> 明日出院 <span class="emr-dc-mode-sub">（自动将长期医嘱停止到所选时间）</span></label>'
        + '</div>'
        + '<div class="emr-dc-dt"><label class="form-label">出院日期时间</label>'
        + '<input id="emr-dc-at" class="form-control" data-dtpicker="datetime" value="' + fmt(defToday) + '"></div>'
        + '<div id="emr-dc-check" class="emr-dc-check">校验中…</div>'
        + '<div class="emr-dc-foot"><button type="button" class="btn btn-primary" id="emr-dc-ok" disabled>确认出院</button>'
        + '<button type="button" class="btn" id="emr-dc-cancel">取消</button></div>'
        + '</div>';
    const overlay = Dom.modal('出院办理', content, []);
    const dtEl = overlay.querySelector('#emr-dc-at');
    const okBtn = overlay.querySelector('#emr-dc-ok');
    const checkEl = overlay.querySelector('#emr-dc-check');
    if (window.DateTimePicker) DateTimePicker.initInputs(overlay);
    let checkOk = false;

    function renderCheck(data) {
        if (data.ok) {
            checkOk = true;
            checkEl.innerHTML = '<div class="emr-dc-ok">校验通过，可以办理出院</div>';
        } else {
            checkOk = false;
            const groups = [];
            if (data.orders.length) groups.push('未核对/待处理医嘱 ' + data.orders.length + ' 条：' + data.orders.slice(0, 3).map(o => Format.escape(o.content)).join('、') + (data.orders.length > 3 ? ' 等' : ''));
            if (data.labs.length) groups.push('未完成检验 ' + data.labs.length + ' 项：' + data.labs.slice(0, 3).map(x => Format.escape(x.exam_name)).join('、') + (data.labs.length > 3 ? ' 等' : ''));
            if (data.exams.length) groups.push('未完成检查 ' + data.exams.length + ' 项：' + data.exams.slice(0, 3).map(x => Format.escape(x.exam_type)).join('、') + (data.exams.length > 3 ? ' 等' : ''));
            if (data.meds.length) groups.push('未发放处方 ' + data.meds.length + ' 条：' + data.meds.slice(0, 3).map(x => Format.escape(x.content)).join('、') + (data.meds.length > 3 ? ' 等' : ''));
            checkEl.innerHTML = '<div class="emr-dc-block">暂不可出院：</div><ul class="emr-dc-list">' + groups.map(g => '<li>' + g + '</li>').join('') + '</ul>';
        }
        updateOk();
    }
    function updateOk() {
        const d = emrParseStamp(dtEl.value);
        const valid = d && d.getTime() >= Date.now();
        if (!valid) checkEl.title = '出院时间不能早于当前时间';
        okBtn.disabled = !(checkOk && valid);
    }
    AjaxLoader.api('emr/discharge-check?patient_id=' + _emrPatient.id, { silent: true }).then((resp) => renderCheck(resp.data || {})).catch((err) => {
        console.error('出院校验失败', err);
        checkEl.innerHTML = '<div class="emr-dc-block">校验失败：' + Format.escape(err && err.message || '请稍后重试') + '</div>';
    });
    dtEl.addEventListener('input', updateOk);
    dtEl.addEventListener('change', updateOk);
    overlay.querySelectorAll('input[name="emr-dc-mode"]').forEach(r => {
        r.addEventListener('change', () => {
            dtEl.value = fmt(r.value === 'tomorrow' ? defTomorrow : defToday);
            updateOk();
        });
    });
    overlay.querySelector('#emr-dc-cancel').addEventListener('click', () => overlay.remove());
    okBtn.addEventListener('click', async () => {
        const mode = (overlay.querySelector('input[name="emr-dc-mode"]:checked') || {}).value || 'today';
        const at = (dtEl.value || '').trim();
        const d = emrParseStamp(at);
        if (!d || d.getTime() < Date.now()) { Dom.toast('请选择不早于当前时间的出院日期时间', 'error'); return; }
        okBtn.disabled = true;
        try {
            const r = await AjaxLoader.api('emr/discharge', { method: 'POST', data: { patient_id: _emrPatient.id, mode: mode, discharge_at: at } });
            const stopped = r.data && r.data.stopped ? ('，已停止 ' + r.data.stopped + ' 条长期医嘱（待护士确认）') : '';
            Dom.toast('已开具「' + (mode === 'today' ? '今日出院' : '明日出院') + '」医嘱' + stopped + '，请护士审核', 'success');
            overlay.remove();
            refreshDocs();
        } catch (e) {
            okBtn.disabled = false; /* AjaxLoader 已提示原因 */
        }
    });
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
    document.getElementById('emr-editor-status').innerHTML =
        '<span class="' + (dirty ? 'emr-sb-dirty' : 'emr-sb-saved') + '">' + (dirty ? '未保存' : '已保存') + '</span>'
        + '<span class="emr-sb-count">字数 ' + chars + '</span>';
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
    bind('btn-discharge', emr_openDischarge);
}

bindToolbar();

window.addEventListener('beforeunload', function(e) {
    if (_emrEditor && _emrEditor.isDirty()) { e.preventDefault(); e.returnValue = ''; }
});
</script>
