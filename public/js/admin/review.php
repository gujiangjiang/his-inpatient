<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="rv-page">
    <h3>审核中心</h3>
    <div class="rv-sec">
        <div class="rv-sec-head">病历模板待审核<span class="rv-count" id="rv-tpls-count"></span></div>
        <div id="rv-tpls"></div>
    </div>
    <div class="rv-sec">
        <div class="rv-sec-head">内容库待审核<span class="rv-count" id="rv-cons-count"></span></div>
        <div id="rv-cons"></div>
    </div>
    <div class="rv-sec">
        <div class="rv-sec-head">病历维护申请<span class="rv-count" id="rv-maint-count"></span></div>
        <div id="rv-maint"></div>
    </div>
</div>
<script>
var _rvKind = ''; /* 防止重复初始化加载 */

async function admin_review_init() {
    if (_rvKind) return;
    _rvKind = 'x';
    await rvLoad('tpls', 'rv-tpls', 'emr/template-list?review=1', '模板');
    await rvLoad('cons', 'rv-cons', 'emr/content-list?review=1', '内容');
    await rvLoadMaint();
}

/** 病历维护申请 (签名病历 编辑/删除): 批准 → 编辑退回草稿 / 删除文书; 驳回附原因 */
async function rvLoadMaint() {
    const r = await AjaxLoader.api('emr/maintenance-list?review=1', { silent: true });
    const items = r.data || [];
    const el = document.getElementById('rv-maint');
    const countEl = document.getElementById('rv-maint-count');
    countEl.textContent = items.length ? '(' + items.length + ')' : '';
    if (!items.length) {
        el.innerHTML = '<div style="padding:18px;color:#909399;font-size:13px">暂无待审核项</div>';
        return;
    }
    el.innerHTML = items.map(function (it) {
        const actTxt = it.action === 'delete' ? '删除' : '编辑';
        const meta = [
            it.patient_name ? it.patient_name + (it.admission_no ? '（' + it.admission_no + '）' : '') : '',
            it.requester_name, (it.requested_at || '').slice(0, 16)
        ].filter(Boolean).join(' · ');
        return '<div class="rv-row">'
            + '<div class="rv-name"><b>' + Format.escape(it.doc_title || '未命名文书') + '</b>'
            + '<span class="rv-prev">原因：' + Format.escape(it.reason || '') + '</span></div>'
            + '<span class="emr-pub-tag ' + (it.action === 'delete' ? 'rej' : 'pend') + '">' + actTxt + '</span>'
            + '<span class="rv-meta">' + Format.escape(meta) + '</span>'
            + '<span class="rv-act"><button type="button" class="btn btn-small btn-success" data-mid="' + it.id + '" data-a="ok">批准</button>'
            + '<button type="button" class="btn btn-small" data-mid="' + it.id + '" data-a="no">驳回</button></span></div>';
    }).join('');
    el.querySelectorAll('button[data-mid]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (b.dataset.a === 'ok') {
                const isDel = (items.find(function (x) { return String(x.id) === String(b.dataset.mid); }) || {}).action === 'delete';
                Dom.modal('批准维护申请', isDel
                    ? '<p>将<b>删除</b>该病历及其全部历史版本，且不可恢复。确定批准吗？</p>'
                    : '<p>将把该病历<b>退回草稿</b>（解锁编辑），修改后需重新签名。确定批准吗？</p>',
                    [
                        { text: '批准', type: 'primary', onclick: function () {
                            AjaxLoader.api('emr/maintenance-review', { method: 'POST', data: { id: b.dataset.mid, action: 'approve' } }).then(function () {
                                Dom.toast('已批准', 'success');
                                rvLoadMaint();
                            });
                        } },
                        { text: '取消', type: 'default' }
                    ]);
                return;
            }
            Dom.modal('驳回维护申请', '<div class="form-group"><label class="form-label">驳回原因（申请人可见）</label><input id="rv-m-reason" class="form-control" placeholder="如：请先联系病案室登记"></div>', [
                { text: '确认驳回', type: 'danger', onclick: function () {
                    AjaxLoader.api('emr/maintenance-review', { method: 'POST', data: { id: b.dataset.mid, action: 'reject', reason: document.getElementById('rv-m-reason').value || '' } }).then(function () {
                        Dom.toast('已驳回', 'success');
                        rvLoadMaint();
                    });
                } },
                { text: '取消', type: 'default' }
            ]);
        });
    });
}

function rvRender(elId, items, kind) {
    const el = document.getElementById(elId);
    const countEl = document.getElementById(elId === 'rv-tpls' ? 'rv-tpls-count' : 'rv-cons-count');
    if (countEl) countEl.textContent = items.length ? '(' + items.length + ')' : '';
    if (!items.length) {
        el.innerHTML = '<div style="padding:18px;color:#909399;font-size:13px">暂无待审核项</div>';
        return;
    }
    el.innerHTML = items.map(function (it) {
        const scopeTxt = it.pub_scope === 'all' ? '全院' : (it.department_name || '科室');
        const meta = [it.category_name, it.creator_name, (it.pub_audited_at || it.updated_at || it.created_at || '').slice(0, 16)].filter(Boolean).join(' · ');
        const prev = kind === '内容' ? (it.content || '').slice(0, 80) : '';
        return '<div class="rv-row">'
            + '<div class="rv-name"><b>' + Format.escape(it.name) + '</b>'
            + '<span class="rv-prev">' + Format.escape(prev) + '</span></div>'
            + '<span class="emr-pub-tag pend">待审核·' + Format.escape(scopeTxt) + '</span>'
            + '<span class="rv-meta">' + Format.escape(meta) + '</span>'
            + '<span class="rv-act"><button type="button" class="btn btn-small btn-success" data-rid="' + it.id + '" data-kind="' + kind + '" data-a="ok">通过</button>'
            + '<button type="button" class="btn btn-small" data-rid="' + it.id + '" data-kind="' + kind + '" data-a="no">驳回</button></span></div>';
    }).join('');
}

function rvBind(kind, items) {
    document.querySelectorAll('[data-kind="' + kind + '"]').forEach(function (b) {
        b.addEventListener('click', function () {
            const id = b.dataset.rid;
            const act = b.dataset.a;
            const apiBase = kind === '模板' ? 'emr/template-review' : 'emr/content-review';
            if (act === 'ok') {
                AjaxLoader.api(apiBase, { method: 'POST', data: { id: id, action: 'approve' } }).then(async function () {
                    Dom.toast('已通过', 'success');
                    await rvLoad(kind === '模板' ? 'tpls' : 'cons', kind === '模板' ? 'rv-tpls' : 'rv-cons', kind === '模板' ? 'emr/template-list?review=1' : 'emr/content-list?review=1', kind);
                });
                return;
            }
            Dom.modal('驳回' + kind, '<div class="form-group"><label class="form-label">驳回原因（创作者可见）</label><input id="rv-reason" class="form-control" placeholder="如：缺少医师签名行"></div>', [
                { text: '确认驳回', type: 'danger', onclick: function () {
                    const reason = (document.getElementById('rv-reason').value || '').trim();
                    AjaxLoader.api(apiBase, { method: 'POST', data: { id: id, action: 'reject', reason: reason } }).then(async function () {
                        Dom.toast('已驳回', 'success');
                        await rvLoad(kind === '模板' ? 'tpls' : 'cons', kind === '模板' ? 'rv-tpls' : 'rv-cons', kind === '模板' ? 'emr/template-list?review=1' : 'emr/content-list?review=1', kind);
                    });
                } },
                { text: '取消', type: 'default' }
            ]);
        });
    });
}

async function rvLoad(kindKey, elId, url, kind) {
    const r = await AjaxLoader.api(url, { silent: true });
    const items = r.data || [];
    rvRender(elId, items, kind);
    rvBind(kind, items);
}
</script>