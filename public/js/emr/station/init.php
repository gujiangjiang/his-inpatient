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
    /* 槽位默认值预填: 下拉/复选用模板勾选的「默认选中」(defPick), 其余用默认内容 (def) */
    EmrOps.walkSlots(doc.body, function (p, slot) {
        if (EmrOps.valueAfterSlot(p, slot.key).trim()) return;
        if (Array.isArray(slot.defPick) && slot.defPick.length) { EmrOps.setSlotValue(p, slot.key, slot.defPick.join('、')); return; }
        if (slot.def) EmrOps.setSlotValue(p, slot.key, slot.def);
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
var _emrOpenSeq = 0;       // 打开文书竞态令牌 (连点/并发响应只认最新)
async function emr_openDocument(docId) {
    clearTimeout(_emrAutoSaveTimer); /* 切换文书: 清理上一文书挂起的自动保存 */
    const mySeq = ++_emrOpenSeq;
    let r;
    try {
        r = await AjaxLoader.api('emr/document-get?id=' + docId, { silent: true });
    } catch (e) {
        Dom.toast('打不开文书: ' + (e && e.message ? e.message : '未知错误'), 'error');
        return;
    }
    if (mySeq !== _emrOpenSeq) return; /* 已被更新的打开请求取代 */
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
    // 旧模型文档 (v<2): 槽位后文本视为值 (val 标记), 保证已填值可改选/参与计算, 保存时自动升级
    if (doc.doc_json && Number(doc.doc_json.v || 0) < EmrDoc.MODEL_V) {
        EmrDoc.legacyValMigrate(doc.doc_json.body || []);
        doc.doc_json.v = EmrDoc.MODEL_V;
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

/** 诊断文本 (按分类过滤+多诊断序号拼接; 入院诊断为空时兜底患者入院诊断; 无记录返回 '') */
async function emrDiagTextOf(category) {
    let rows = [];
    try {
        const r = await AjaxLoader.api('emr/diagnosis-list?patient_id=' + _emrPatient.id, { silent: true });
        rows = r.data || [];
    } catch (e) { rows = []; }
    const list = rows.filter(function (x) { return x.category === category; });
    if (!list.length && category === 'admission' && _emrPatient && _emrPatient.admission_diagnosis) {
        return String(_emrPatient.admission_diagnosis);
    }
    return list.map(function (x, i) { return list.length > 1 ? (i + 1) + '.' + x.name : x.name; }).join('；');
}

/** 诊断引用元素自动填充: diag_admission/diag_discharge dyn 取左侧「诊断」模块 (未签文档每次打开刷新) */
async function syncDiagDyns(doc) {
    const need = ['diag_admission', 'diag_discharge'].filter(function (k) {
        return EmrOps.findParaWithItem(doc.body, 'dyn', k);
    });
    if (!need.length) return;
    if (need.indexOf('diag_admission') >= 0) EmrOps.setDynTextByKey(doc.body, 'diag_admission', await emrDiagTextOf('admission'));
    if (need.indexOf('diag_discharge') >= 0) EmrOps.setDynTextByKey(doc.body, 'diag_discharge', await emrDiagTextOf('discharge'));
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
</script>
