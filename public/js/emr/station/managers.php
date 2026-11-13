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
                if (!name) { Dom.toast('请输入模板名称', 'error'); return false; }
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
}

/** 模板管理: 本科室(设计/删除) / 全院(复制到本科室) —— 通用管理器外壳驱动 */
var emr_openTemplateManager = EmrUI.managedList({
    title: '病历模板管理',
    listUrl: 'emr/template-list?structured=1',
    catsUrl: 'emr/category-list',
    myDeptId: emrMyDeptId(), /* 读取时已登录; isOwn 亦动态判定 */
    deptNameOf: function (t) { return emrDeptNameOf(t); },
    isOwn: function (t) { return t.department_id != null && String(t.department_id) === emrMyDeptId(); },
    deptKeyOf: function (t) { return t.department_id == null ? '0' : String(t.department_id); },
    catKeyOf: function (t) { return t.category_id == null ? 'none' : String(t.category_id); },
    nameOf: function (t) { return t.name; },
    unitLabel: '个模板',
    actionsHtml: function (t, own) {
        return own
            ? '<span class="mini" data-a="design">设计</span><span class="mini danger" data-a="del">删除</span>'
            : '<span class="mini" data-a="clone">复制到本科室</span>';
    },
    emptyText: function (catName, deptName, tab) {
        return tab === 'mine'
            ? '该分类暂无本科室模板：可切换到「全院」将模板复制到本科室，或点击下方"新建模板"创建'
            : '「' + EmrUI.esc(deptName) + '」下该分类暂无模板';
    },
    newLabel: '新建模板',
    onNew: function (api, defCat) { emr_tplNewDialog(api, defCat); },
    onRowAction: async function (a, t, api) {
        if (a === 'design') { api.close(); emr_enterTemplateDesign(t.id); return; }
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

/** 内容库管理: 本科室(编辑/删除) / 全院(复制到本科室, 三列科室列) —— 通用管理器外壳驱动 */
var emr_openContentManager = EmrUI.managedList({
    title: '病历内容库',
    listUrl: 'emr/content-list',
    catsUrl: 'emr/category-list',
    myDeptId: emrMyDeptId(),
    deptNameOf: function (t) { return emrDeptNameOf(t); },
    isOwn: function (t) { return t.department_id != null && String(t.department_id) === emrMyDeptId(); },
    deptKeyOf: function (t) { return t.department_id == null ? '0' : String(t.department_id); },
    catKeyOf: function (t) { return t.category_id == null ? 'none' : String(t.category_id); },
    nameOf: function (t) { return t.name; },
    unitLabel: '条内容',
    tagsHtml: function (t) {
        return t.category_name ? '<span class="emr-tpm-rdept">' + Format.escape(t.category_name) + '</span>' : '';
    },
    actionsHtml: function (t, own) {
        return own
            ? '<span class="mini" data-a="edit">编辑</span><span class="mini danger" data-a="del">删除</span>'
            : '<span class="mini" data-a="clone">复制到本科室</span>';
    },
    emptyText: function (catName, deptName, tab) {
        return tab === 'mine'
            ? '该分类暂无本科室内容：可切换到「全院」将内容复制到本科室，或点击下方"新建内容"创建（纯文本片段，写病历时可整段或选段写入）'
            : '「' + EmrUI.esc(deptName) + '」下该分类暂无内容';
    },
    newLabel: '新建内容',
    onNew: function (api, defCat) {
        emr_contentEditDialog({ id: null, name: '', category_id: defCat, content: '' }, api.cats, api.reload, api.rerender);
    },
    onRowAction: async function (a, t, api) {
        if (a === 'edit') { emr_contentEditDialog(t, api.cats, api.reload, api.rerender); return; }
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
