<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">审核中心</h3>
        <p class="page-desc">病历模板 · 内容库 · 病历维护申请 待审核与处理记录</p>
    </div>
    <button class="btn btn-success" id="rv-all-btn" style="display:none" onclick="admin_review_all()">一键全部通过</button>
</div>

<div class="stat-cards" id="rv-stats"></div>

<div class="card" style="padding:10px 16px">
    <div class="sub-tabs">
        <button class="sub-tab active" data-rvtab="pending" onclick="admin_review_tab('pending')">待审核</button>
        <button class="sub-tab" data-rvtab="handled" onclick="admin_review_tab('handled')">已处理</button>
    </div>
</div>

<div class="rv-page" id="rv-pending">
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
<div id="rv-handled" class="hidden">
    <div class="card"><div class="card-header">已处理记录</div><div id="rv-handled-body"></div></div>
</div>
<script>
var _rvKind = ''; /* 防止重复初始化加载 */

async function admin_review_init() {
    if (_rvKind) return;
    _rvKind = 'x';
    await rvLoad('tpls', 'rv-tpls', 'emr/template-list?review=1', '模板');
    await rvLoad('cons', 'rv-cons', 'emr/content-list?review=1', '内容');
    await rvLoadMaint();
    rvRenderStats();
}

function admin_review_tab(t) {
    document.querySelectorAll('.sub-tab[data-rvtab]').forEach(function (b) {
        b.classList.toggle('active', b.dataset.rvtab === t);
    });
    document.getElementById('rv-pending').classList.toggle('hidden', t !== 'pending');
    document.getElementById('rv-handled').classList.toggle('hidden', t !== 'handled');
    if (t === 'handled') admin_review_loadHandled();
}

function rvRenderStats() {
    const box = document.getElementById('rv-stats');
    const tpls = _rvTplsLen || 0, cons = _rvConsLen || 0, maint = _rvMaintLen || 0;
    const total = tpls + cons + maint;
    box.innerHTML = [
        ['待审核模板', tpls], ['待审核内容', cons], ['维护申请', maint], ['合计', total]
    ].map(function (s) { return '<div class="stat-card"><div class="stat-num">' + s[1] + '</div><div class="stat-label">' + s[0] + '</div></div>'; }).join('');
    document.getElementById('rv-all-btn').style.display = total > 0 ? '' : 'none';
}

async function admin_review_loadHandled() {
    const box = document.getElementById('rv-handled-body');
    const r = await AjaxLoader.api('admin/review-history', { silent: true });
    const list = (r.data && r.data.list) || [];
    if (!list.length) { box.innerHTML = '<div class="empty-tip">暂无已处理记录</div>'; return; }
    const rows = list.map(function (h) {
        const statusBadge = h.status === 'approved'
            ? '<span class="badge badge-success">已通过</span>'
            : (h.status === 'rejected' ? '<span class="badge badge-danger">已驳回</span>' : '<span class="badge badge-default">' + Format.escape(h.status) + '</span>');
        const meta = [h.type, h.audited_by, h.audited_at ? Format.datetime(h.audited_at) : ''].filter(Boolean).join(' · ');
        return '<div class="rv-row">'
            + '<div class="rv-name"><b>' + Format.escape(h.name) + '</b>'
            + (h.note ? '<span class="rv-prev">驳回原因：' + Format.escape(h.note) + '</span>' : '') + '</div>'
            + statusBadge
            + '<span class="rv-meta">' + Format.escape(meta) + '</span></div>';
    }).join('');
    box.innerHTML = '<div class="rv-holder">' + rows + '</div>';
}

async function admin_review_all() {
    Dom.modal('一键全部通过', '<p>将全部待审核的模板 / 内容库通过审核。确定吗？</p>', [
        { text: '全部通过', type: 'primary', onclick: async function () {
            const ps = [
                ...document.querySelectorAll('#rv-tpls button[data-rid][data-a="ok"]').length ? [Promise.resolve('tpls')] : [],
            ];
            Dom.toast('请逐个通过（模板/内容库审批接口逐条处理）', 'info');
            return false;
        } },
        { text: '取消', type: 'default' }
    ]);
}

/** 病历维护申请 (签名病历 编辑/删除): 批准 → 编辑退回草稿 / 删除文书; 驳回附原因 */
async function rvLoadMaint() {
    const r = await AjaxLoader.api('emr/maintenance-list?review=1', { silent: true });
    const items = r.data || [];
    _rvMaintLen = items.length;
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
    if (kindKey === 'tpls') _rvTplsLen = items.length;
    else if (kindKey === 'cons') _rvConsLen = items.length;
    rvRender(elId, items, kind);
    rvBind(kind, items);
}
</script>