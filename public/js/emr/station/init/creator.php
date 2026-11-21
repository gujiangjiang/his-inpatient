<script>
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

    const nowStamp = Format.nowStamp();
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
    HospitalBranding.stampHeader(doc.header);
    /* 文书标题优先取模板的抬头 doc_name (与模板名称分离); 上级医师查房等可显式指定 */
    doc.title = opts.title || (doc.header && doc.header.doc_name) || template.name;
    /* 槽位默认值预填: 下拉/复选用模板勾选的「默认选中」(defPick), 其余用默认内容 (def) */
    EmrOps.walkSlots(doc.body, function (p, slot) {
        if (EmrOps.valueAfterSlot(p, slot.key).trim()) return;
        if (Array.isArray(slot.defPick) && slot.defPick.length) { EmrOps.setSlotValue(p, slot.key, slot.defPick.join('、')); return; }
        if (slot.def) EmrOps.setSlotValue(p, slot.key, slot.def);
    });
    /* 记录日期 = 模板选择时指定的日期时间 (默认当前时间; 可点击修改; 不参与首页同步) */
    EmrOps.setSlotValueByKey(doc.body, 'p_recorddate_val', opts.datetime || Format.nowStamp());
    /* 字段引用: 仅在首次创建时自动调用一次 (保存即固化, 之后打开不再变化) */
    try { await resolveRefFields(doc); } catch (e) { console.error('字段引用解析失败', e); }
    /* 自动计算项: 预填/引用完成后按当前文书字段刷新一次 */
    EmrOps.recalcCalcs(doc.body);
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
    const age = Format.ageSimple(p.birth_date);
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
</script>
