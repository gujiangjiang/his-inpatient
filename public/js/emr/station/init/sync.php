<script>
/* ================= 病案首页单项同步 (仅编辑状态; 已签名=法律快照不动) ================= */
/* 时间戳工具 emrNowStamp/emrYMD/emrStamp 已下沉 Format.nowStamp/ymd/stamp (core/format.js) */
/* 固化字段 (dyn 只读): 用户不可修改, 始终自动跟随病案首页 */
var CASE_DYN = {
    p_name: (c, p) => c.name || (p && p.name) || '',
    p_gender: (c, p) => ({ '1': '男', '2': '女' }[c.gender]) || (p && p.gender === 'male' ? '男' : p && p.gender === 'female' ? '女' : (p && (p.gender === '男' || p.gender === '女') ? p.gender : '')),
    p_age: (c, p) => {
        if (p && p.birth_date) return Format.ageClinical(p.birth_date);
        if (c.age_y) return String(c.age_y) + '岁';
        if (c.age_m) return String(c.age_m) + '月';
        if (c.age_d !== '' && c.age_d != null) return String(c.age_d) + '天';
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
    p_admitdate_val: (c, p) => Format.stamp((p && p.admission_date) || c.admit_time || ''),
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

/** 分类诊断文本 (多诊断带序号拼接; 无记录返回 '') */
async function emrDiagTextOf(category) {
    let rows = [];
    try {
        const r = await AjaxLoader.api('emr/diagnosis-list?patient_id=' + _emrPatient.id, { silent: true });
        rows = r.data || [];
    } catch (e) { rows = []; }
    const list = rows.filter(function (x) { return x.category === category; });
    return list.map(function (x, i) { return list.length > 1 ? (i + 1) + '.' + x.name : x.name; }).join('；');
}

/** 病历自动引用诊断口径: 修正 > 入院 > 初步 (不含门急诊); {text, cat, label} 或 null */
async function emrDiagRefInfo() {
    let rows = [];
    try {
        const r = await AjaxLoader.api('emr/diagnosis-list?patient_id=' + _emrPatient.id, { silent: true });
        rows = r.data || [];
    } catch (e) { rows = []; }
    const order = [['corrected', '修正诊断'], ['admission', '入院诊断'], ['initial', '初步诊断']];
    for (const pair of order) {
        const list = rows.filter(function (x) { return x.category === pair[0]; });
        if (list.length) {
            return {
                cat: pair[0], label: pair[1],
                text: list.map(function (x, i) { return list.length > 1 ? (i + 1) + '.' + x.name : x.name; }).join('；')
            };
        }
    }
    return null;
}

/** 诊断引用元素自动填充 (未签文档每次打开刷新):
 *  diag_admission / initial_diagnosis_value → 优先级口径 修正 > 入院 > 初步 (不含门急诊)
 *  diag_discharge → 出院诊断分类 */
async function syncDiagDyns(doc) {
    const needAdm = EmrOps.findParaWithItem(doc.body, 'dyn', 'diag_admission');
    const needInit = EmrOps.findParaWithItem(doc.body, 'dyn', 'initial_diagnosis_value');
    const needDis = EmrOps.findParaWithItem(doc.body, 'dyn', 'diag_discharge');
    if (!needAdm && !needInit && !needDis) return;
    if (needAdm || needInit) {
        const info = await emrDiagRefInfo();
        const text = info ? info.text : '';
        if (needAdm) EmrOps.setDynTextByKey(doc.body, 'diag_admission', text);
        if (needInit) EmrOps.setDynTextByKey(doc.body, 'initial_diagnosis_value', text);
    }
    if (needDis) EmrOps.setDynTextByKey(doc.body, 'diag_discharge', await emrDiagTextOf('discharge'));
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
    if (!signed && doc.doc_json) HospitalBranding.stampHeader(doc.doc_json.header || (doc.doc_json.header = {}));
    applyPageCss(doc.doc_json.page);
    _emrEditMode = !signed;
    /* 编辑状态: 患者信息与病案首页单项同步 (已签名=快照, 不动) */
    if (!signed && doc.doc_json && Array.isArray(doc.doc_json.body)) {
        try { await syncFromFrontPage(doc.doc_json); } catch (e) { console.error('病案首页同步失败', e); }
        try { await syncDiagDyns(doc.doc_json); } catch (e) { console.error('诊断引用填充失败', e); }
    }
    const metaGuard = doc.doc_meta && doc.doc_meta.guard && Array.isArray(doc.doc_meta.guard.fx) ? doc.doc_meta.guard : null;
    /* 修订模式: 签名后经维护批准解锁的文书 (doc_meta.revise) — 删除留痕, 打印不输出 */
    _emrReviseMode = !signed && !!(doc.doc_meta && doc.doc_meta.revise);
    _emrEditor = EmrEditor.create({
        container: document.getElementById('emr-editor-canvas'),
        doc: doc.doc_json,
        guard: metaGuard || EmrDoc.collectGuard(doc.doc_json.body || []),
        patientInfo: buildPatientInfo(),
        readonly: signed,
        trackChanges: _emrReviseMode,
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
    /* 内容渲染完成后再次重算窄屏画布缩放 (兜底: 任何路径打开文书都保证缩放正确) */
    if (typeof emr_fitEditorZoom === 'function') emr_fitEditorZoom();
}
</script>
