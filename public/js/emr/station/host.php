<script>
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

/* ================= 右键菜单 / 删除 / 病历维护 ================= */
function emr_showContextMenu(e, doc) {
    const items = [{ a: 'open', label: '打开' }];
    if (doc.status !== 'signed') {
        items.push({ a: 'del', label: '删除', danger: true });
    } else {
        /* 已签名病历: 删除/编辑均需「病历维护」申请, 管理员审核通过后解锁 */
        items.push({ a: 'maint', label: doc.maint_pending_id ? '病历维护（待审核）' : '病历维护…' });
    }
    EmrUI.floatMenu(items, e.clientX, e.clientY, function (a) {
        if (a === 'open') emr_openDocument(doc.id);
        else if (a === 'del') emr_deleteDoc(doc.id);
        else if (a === 'maint') emr_maintDialog(doc);
    });
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
    bind('btn-versions', emr_showVersions);
    bind('btn-sign', emr_signDoc);
    bind('btn-template-mgr', () => emr_openTemplateManager());
    bind('btn-content-mgr', () => emr_openContentManager());
    bind('btn-maint-mgr', emr_maintListDialog);
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
    bind('dsn-sec-calc', function () {
        const head = document.getElementById('dsn-sec-calc');
        const open = head.classList.toggle('open');
        document.getElementById('dsn-calc').style.display = open ? '' : 'none';
    });
    (function () {
        const box = document.getElementById('dsn-calc');
        box.querySelectorAll('.emr-dsn-field').forEach(function (el) {
            el.addEventListener('click', function () { emr_dsnInsertCalc(); });
        });
    })();
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
    bind('btn-dsn-import', emr_tplImport);
    bind('btn-dsn-export', emr_tplExport);
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
