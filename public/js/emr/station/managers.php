<script>
/** 当前登录者科室 id (字符串; 无则 null) */
function emrMyDeptId() {
    return Auth.user && Auth.user.department_id ? String(Auth.user.department_id) : null;
}

/** 新建模板对话框 (两列: 名称/分类 + 基础模板卡片单选) */
function emr_tplNewDialog(api, defCat) {
    const cats = api.cats;
    const catOpts = cats.map(function (c) {
        return '<option value="' + c.id + '"' + (String(c.id) === defCat ? ' selected' : '') + '>' + Format.escape(c.name) + '</option>';
    }).join('');
    let selBase = ''; /* '' = 空白模板 (任何分类都提供) */
    function baseRows(catId) {
        const list = api.items.filter(function (t) { return String(t.category_id) === String(catId); })
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
        + '<div class="form-group"><label class="form-label">模板名称（列表管理与选择用）</label><input id="emr-newtpl-name" class="form-control" placeholder="如：入院记录（男）"></div>'
        + '<div class="form-group"><label class="form-label">文书标题（病历抬头，医院名称下方）</label><input id="emr-newtpl-title" class="form-control" placeholder="如：入院记录"></div>'
        + '<div class="form-group"><label class="form-label">所属分类</label><select id="emr-newtpl-cat" class="form-control">' + catOpts + '</select></div>'
        + '<p class="emr-tpl-hint">模板名称用于管理与选择（如「入院记录（男）」）；文书标题是新建病历的实际标题（如「入院记录」）。可选择右侧现有模板作为底稿，也可空白模板从零发挥。</p>'
        + '</div>'
        + '<div class="emr-newtpl-right"><div class="emr-newtpl-rhead">基础模板</div>'
        + '<div class="emr-newtpl-bases" id="emr-newtpl-bases">' + baseRows(defCat) + '</div></div>'
        + '</div>',
        [
            { text: '创建并设计', type: 'primary', onclick: async function () {
                const name = (document.getElementById('emr-newtpl-name').value || '').trim();
                const docTitle = (document.getElementById('emr-newtpl-title').value || '').trim() || name;
                const cid = document.getElementById('emr-newtpl-cat').value;
                if (!name) { Dom.toast('请输入模板名称', 'error'); return false; }
                const cat = cats.find(function (c) { return String(c.id) === String(cid); });
                let schema = {
                    mode: 'word', title: docTitle,
                    page: Object.assign({}, EmrDoc.DEFAULT_PAGE),
                    header: { hospital: '', doc_name: docTitle, info_fields: EmrDoc.DEFAULT_INFO_FIELDS },
                    body: [
                        EmrDoc.para([EmrDoc.text(docTitle, { b: true, sz: 14 })], { align: 'center' }),
                        EmrDoc.para([EmrDoc.fx('sign_line', '医师签名：'), EmrDoc.item('dyn', { key: 'sign_names', text: '' })], { align: 'right' })
                    ]
                };
                /* 以现有模板为底稿: 复制其版式/正文, 仅替换名称与文书标题 (科室归属自动为本科室)
                 *  mv 原样保留: 旧模型模板进入设计器时再迁移值标记 */
                if (selBase) {
                    try {
                        const tr = await AjaxLoader.api('emr/template-get?id=' + selBase, { silent: true });
                        const bs = parseSchema(tr.data && tr.data.schema_json);
                        if (bs && bs.mode === 'word') {
                            schema = {
                                mode: 'word', mv: bs.mv, title: docTitle, page: bs.page,
                                header: Object.assign({}, bs.header, { doc_name: docTitle }),
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
                api.close();
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
    const catSel = sub.querySelector('#emr-newtpl-cat');
    catSel.addEventListener('change', function () {
        selBase = '';
        basesEl.innerHTML = baseRows(catSel.value);
        bindBases();
    });
    /* 文书标题默认跟随模板名称 (手动修改后不再联动) */
    const nameEl = sub.querySelector('#emr-newtpl-name');
    const titleEl = sub.querySelector('#emr-newtpl-title');
    let titleTouched = false;
    titleEl.addEventListener('input', function () { titleTouched = true; });
    nameEl.addEventListener('input', function () { if (!titleTouched) titleEl.value = nameEl.value; });
}

/** 模板属性: 修改模板名称与文书标题 (文书标题 = header.doc_name, 新建文书抬头显示) */
async function emr_tplPropsDialog(t, api) {
    let tr;
    try { tr = await AjaxLoader.api('emr/template-get?id=' + t.id, { silent: true }); } catch (e) { tr = null; }
    const full = (tr && tr.data) || {};
    const schema = parseSchema(full.schema_json) || { mode: 'word' };
    const docTitle = (schema.header && schema.header.doc_name) || t.name;
    const content =
        '<div class="form-group"><label class="form-label">模板名称（模板列表显示用）</label>'
        + '<input id="tpm-name" class="form-control" value="' + Format.escape(t.name) + '"></div>'
        + '<div class="form-group"><label class="form-label">文书标题（新建文书标题，显示在医院名称下方）</label>'
        + '<input id="tpm-title" class="form-control" value="' + Format.escape(docTitle) + '"></div>'
        + '<p class="emr-tpl-hint">如模板名称「入院记录（男）」的文书标题为「入院记录」：模板名称用于管理与选择，文书标题是实际病历的抬头。</p>';
    const sub = Dom.modal('模板属性', content, [
        { text: '保存', type: 'primary', onclick: async function () {
            const name = (sub.querySelector('#tpm-name').value || '').trim();
            const dtitle = (sub.querySelector('#tpm-title').value || '').trim();
            if (!name) { Dom.toast('请输入模板名称', 'error'); return false; }
            if (!dtitle) { Dom.toast('请输入文书标题', 'error'); return false; }
            schema.title = dtitle;
            schema.header = Object.assign({}, schema.header || {}, { doc_name: dtitle });
            await AjaxLoader.api('emr/template-save', { method: 'POST', data: {
                id: t.id, name: name, record_type: t.record_type,
                content_delta: full.content_delta || '{}', content_html: full.content_html || '',
                schema_json: JSON.stringify(schema), category_id: t.category_id
            } });
            Dom.toast('模板属性已更新', 'success');
            await api.reload();
            api.rerender();
        } },
        { text: '取消', type: 'default' }
    ]);
    sub.querySelector('#tpm-name').focus();
}

/** 模板管理: 本科室(设计/删除) / 全院(复制到本科室) —— 通用管理器外壳驱动 */
/** 是否本人创建 (编辑/发布/删除 权限判定) */
function emrIsCreator(t) {
    const me = Auth.user && Auth.user.id;
    return !!me && String(t.created_by) === String(me);
}

/** 本科室 Tab 过滤: 仅归属本科室的模板/内容 (department_id = 本人科室);
 *  全院通用 (department_id 为空) 与他人科室条目一律归「全院」Tab, 不与本科室混排 */
function emrMineUsable(t) {
    const my = emrMyDeptId();
    return !!my && t.department_id != null && String(t.department_id) === String(my);
}

/** 行内发布状态徽标 */
function emrStatusTag(t) {
    if (t.pub_status === 'personal') return '<span class="emr-pub-tag pers">个人</span>';
    if (t.pub_status === 'pending') return '<span class="emr-pub-tag pend">待审核</span>';
    if (t.pub_status === 'rejected') return '<span class="emr-pub-tag rej" title="' + EmrUI.esc(t.pub_reject_reason || '') + '">未通过</span>';
    if (t.pub_scope === 'all') return '<span class="emr-pub-tag all">全院</span>';
    return '<span class="emr-pub-tag dept">科室</span>';
}

/** 发布对话框 (模板/内容): 选择 科室用/全院用 → 提交审核 */
function emr_pubDialog(item, kind, api) {
    const deptName = emrMyDeptId() ? (item.department_name || '本科室') : '本科室';
    const sub = Dom.modal('发布' + kind + '「' + Format.escape(item.name || '') + '」',
        '<div class="form-group"><label class="form-label">发布范围</label>'
        + '<div class="emr-prop-radios">'
        + '<label class="emr-prop-radio"><input type="radio" name="pub-scope" value="department" checked> 科室用（' + Format.escape(deptName) + '）</label>'
        + '<label class="emr-prop-radio"><input type="radio" name="pub-scope" value="all"> 全院用</label>'
        + '</div></div>'
        + '<p class="emr-tpl-hint">提交后需管理员在「审核中心」审核；通过后「科室用」对本科室可见，「全院用」对全院可见。未通过可修改后重新发布。</p>',
        [
            { text: '提交审核', type: 'primary', onclick: async function () {
                const scope = sub.querySelector('input[name="pub-scope"]:checked').value;
                await AjaxLoader.api(kind === '内容' ? 'emr/content-publish' : 'emr/template-publish', { method: 'POST', data: { id: item.id, scope: scope } });
                Dom.toast('已提交审核', 'success');
                await api.reload();
                api.rerender();
            } },
            { text: '取消', type: 'default' }
        ]);
}

/** 行内操作 (own=本人创建): 编辑 + 发布(个人/未通过时) + 删除; 他人: 复制到本科室
 *  withProps: 模板管理额外提供「属性」(模板名称/文书标题, 管理员对他人模板同样可改) */
function emrRowActions(t, own, withProps) {
    if (!own) {
        const clone = '<span class="mini" data-a="clone">复制到本科室</span>';
        return (withProps && Auth.user && Auth.user.role === 'admin') ? '<span class="mini" data-a="props">属性</span>' + clone : clone;
    }
    let h = '<span class="mini" data-a="edit">编辑</span>';
    if (withProps) h += '<span class="mini" data-a="props">属性</span>';
    if (t.pub_status === 'personal' || t.pub_status === 'rejected') h += '<span class="mini" data-a="publish">发布</span>';
    h += '<span class="mini danger" data-a="del">删除</span>';
    return h;
}

/** 模板管理: 本科室(编辑/发布/删除) / 全院(复制到本科室) —— 通用管理器外壳驱动 */
var emr_openTemplateManager = EmrUI.managedList({
    title: '病历模板管理',
    listUrl: 'emr/template-list?structured=1',
    catsUrl: 'emr/category-list',
    deptsUrl: 'admin/department-list',
    myDeptId: emrMyDeptId(),
    deptNameOf: function (t) { return emrDeptNameOf(t); },
    isOwn: emrIsCreator,
    mineFilter: emrMineUsable,
    statusTag: emrStatusTag,
    deptKeyOf: function (t) { return t.department_id == null ? '0' : String(t.department_id); },
    catKeyOf: function (t) { return t.category_id == null ? 'none' : String(t.category_id); },
    nameOf: function (t) { return t.name; },
    unitLabel: '个模板',
    actionsHtml: function (t, own) { return emrRowActions(t, own, true); },
    emptyText: function (catName, deptName, tab) {
        return tab === 'mine'
            ? '该分类暂无本科室可用模板：可切换到「全院」复制模板到本科室，或点击下方"新建模板"创建'
            : '「' + EmrUI.esc(deptName) + '」下该分类暂无模板';
    },
    newLabel: '新建模板',
    onNew: function (api, defCat) { emr_tplNewDialog(api, defCat); },
    onRowAction: async function (a, t, api) {
        if (a === 'edit') { api.close(); emr_enterTemplateDesign(t.id); return; }
        if (a === 'props') { emr_tplPropsDialog(t, api); return; }
        if (a === 'publish') { emr_pubDialog(t, '模板', api); return; }
        if (a === 'del') {
            Dom.modal('删除模板', '<p>确认删除模板「' + Format.escape(t.name) + '」？引用该模板的文书不受影响（内容独立保存）。</p>', [
                { text: '删除', type: 'danger', onclick: async function () {
                    await AjaxLoader.api('emr/template-delete?id=' + t.id, { method: 'POST' });
                    Dom.toast('已删除', 'success');
                    await api.reload();
                    api.rerender();
                } },
                { text: '取消', type: 'default' }
            ]);
            return;
        }
        if (a === 'clone') {
            const tr = await AjaxLoader.api('emr/template-get?id=' + t.id, { silent: true });
            await AjaxLoader.api('emr/template-save', { method: 'POST', data: {
                name: t.name + '（本科室副本）', record_type: t.record_type,
                content_delta: '{}', content_html: '',
                schema_json: tr.data ? tr.data.schema_json : '{}',
                category_id: t.category_id
            }});
            Dom.toast('已复制到本科室', 'success');
            await api.reload();
            api.rerender();
        }
    }
});

/** 内容库管理: 本科室(编辑/发布/删除) / 全院(复制到本科室, 三列科室列) —— 通用管理器外壳驱动 */
var emr_openContentManager = EmrUI.managedList({
    title: '病历内容库',
    listUrl: 'emr/content-list',
    catsUrl: 'emr/category-list',
    deptsUrl: 'admin/department-list',
    myDeptId: emrMyDeptId(),
    deptNameOf: function (t) { return emrDeptNameOf(t); },
    isOwn: emrIsCreator,
    mineFilter: emrMineUsable,
    statusTag: emrStatusTag,
    deptKeyOf: function (t) { return t.department_id == null ? '0' : String(t.department_id); },
    catKeyOf: function (t) { return t.category_id == null ? 'none' : String(t.category_id); },
    nameOf: function (t) { return t.name; },
    unitLabel: '条内容',
    tagsHtml: function (t) {
        return t.category_name ? '<span class="emr-tpm-rdept">' + Format.escape(t.category_name) + '</span>' : '';
    },
    actionsHtml: emrRowActions,
    emptyText: function (catName, deptName, tab) {
        return tab === 'mine'
            ? '该分类暂无本科室可用内容：可切换到「全院」复制内容到本科室，或点击下方"新建内容"创建（纯文本片段，写病历时可整段或选段写入）'
            : '「' + EmrUI.esc(deptName) + '」下该分类暂无内容';
    },
    newLabel: '新建内容',
    onNew: function (api, defCat) {
        emr_contentEditDialog({ id: null, name: '', category_id: defCat, content: '' }, api.cats, api.reload, api.rerender);
    },
    onRowAction: async function (a, t, api) {
        if (a === 'edit') { emr_contentEditDialog(t, api.cats, api.reload, api.rerender); return; }
        if (a === 'publish') { emr_pubDialog(t, '内容', api); return; }
        if (a === 'del') {
            Dom.modal('删除内容', '<p>确认删除内容「' + Format.escape(t.name) + '」？</p>', [
                { text: '删除', type: 'danger', onclick: async function () {
                    await AjaxLoader.api('emr/content-delete?id=' + t.id, { method: 'POST' });
                    Dom.toast('已删除', 'success');
                    await api.reload();
                    api.rerender();
                } },
                { text: '取消', type: 'default' }
            ]);
            return;
        }
        if (a === 'clone') {
            await AjaxLoader.api('emr/content-save', { method: 'POST', data: {
                name: t.name + '（本科室副本）', category_id: t.category_id, content: t.content || ''
            }});
            Dom.toast('已复制到本科室', 'success');
            await api.reload();
            api.rerender();
        }
    }
});
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
                if (!name) { Dom.toast('请输入内容标题', 'error'); return false; }
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

</script>
