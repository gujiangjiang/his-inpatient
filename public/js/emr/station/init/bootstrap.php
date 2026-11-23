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
var _emrReviseMode = false;   // 修订留痕模式 (签名后经维护批准解锁的文书)
var _emrCategories = _emrCategories || [];

/** 病历树移动端抽屉开关 (仅窄屏使用): 显示切换按钮 */
function emr_treeToggle() {
    const tree = document.getElementById('emr-tree');
    if (!tree) return;
    tree.classList.toggle('open');
}
function emr_treeOpen() {
    const tree = document.getElementById('emr-tree');
    if (tree) tree.classList.add('open');
}
/** 移动端病历树/开关按钮显隐 (页面加载与 resize 时调用) */
function emr_treeFitMobile() {
    const isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
    const openBtn = document.getElementById('emr-tree-openbtn');
    const openBar = document.getElementById('emr-tree-openbar');
    const closeBtn = document.getElementById('emr-tree-toggle');
    const tree = document.getElementById('emr-tree');
    if (isMobile) {
        if (openBtn) openBtn.style.display = '';
        if (openBar) openBar.style.display = '';
        if (closeBtn) closeBtn.style.display = '';
    } else {
        if (openBtn) openBtn.style.display = 'none';
        if (openBar) openBar.style.display = 'none';
        if (closeBtn) closeBtn.style.display = 'none';
        if (tree) tree.classList.remove('open');
    }
    /* 空态提示按端差异化: 移动端病历树为抽屉, 不存在"左侧" */
    const hint = document.getElementById('emr-empty-hint');
    if (hint) {
        hint.innerHTML = isMobile
            ? '点击下方按钮打开病历目录，选择或新建病历文书'
            : '请从左侧选择病历文书，或点击分类后的 <b>＋</b> 从模板新建';
    }
}

/* ================= 初始化 / 树 ================= */
async function emr_station_init() {
    _emrPatient = PatientContext.current();
    if (!_emrPatient) { Dom.toast('请先选择患者', 'error'); return; }
    await loadCategories();
    await loadDocuments();
    await loadQc();
    renderCategoryTree();
    emr_treeFitMobile();
    /* 病历树抽屉: 点击任意文档/分类后收起 */
    const tree = document.getElementById('emr-tree');
    if (tree) {
        tree.addEventListener('click', function (e) {
            if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches
                && (e.target.closest('.emr-doc-item') || e.target.closest('.emr-cat-header'))) {
                tree.classList.remove('open');
            }
        });
    }
    /* 跨断点切换 (桌面↔窄屏) 时同步病历树开关显隐 */
    if (window._emrFitBound) return;
    window._emrFitBound = true;
    window.addEventListener('resize', function () { emr_treeFitMobile(); });
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

/** 文书列表与质控统一刷新 (已离开病历页时仅保存, 不刷新 UI: 自动保存可能在切换页面后完成) */
async function refreshDocs() {
    if (!document.getElementById('emr-category-tree')) return;
    await loadDocuments();
    await loadQc();
    renderCategoryTree();
}

function renderCategoryTree() {
    const container = document.getElementById('emr-category-tree');
    if (!container) return; /* 页面已切换 (SPA): 元素不存在直接跳过 */
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
                '<span class="emr-doc-time">' + Format.escape(Format.stamp(d.updated_at || d.created_at || '')) + '</span>' +
                (d.maint_pending_id ? '<span class="emr-doc-maint" title="维护申请待管理员审核">维护待审</span>' : '') +
                '<span class="emr-doc-status-tag ' + (d.status === 'signed' ? 'signed' : 'draft') + '">' + (d.status === 'signed' ? '已签名' : '草稿') + '</span>';
            item.onclick = () => emr_openDocument(d.id);
            item.oncontextmenu = (e) => { e.preventDefault(); emr_showContextMenu(e, d); };
            docList.appendChild(item);
        });
        group.appendChild(docList);
        container.appendChild(group);
    });
}
</script>
