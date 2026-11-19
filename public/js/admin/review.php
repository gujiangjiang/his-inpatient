<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">审核中心</h3>
        <p class="page-desc">待审核与已处理记录 · 支持日期范围筛选（默认近 7 天，跨度 ≤ 1 个月）</p>
    </div>
    <button class="btn btn-success" id="rv-all-btn" style="display:none" onclick="admin_review_all()">一键全部通过</button>
</div>

<div class="stat-cards" id="rv-stats"></div>

<div class="card" style="padding:10px 16px">
    <div class="flex-between" style="flex-wrap:wrap;gap:10px">
        <div class="sub-tabs">
            <button class="sub-tab active" data-rvtab="pending" onclick="admin_review_tab('pending')">待审核</button>
            <button class="sub-tab" data-rvtab="handled" onclick="admin_review_tab('handled')">已处理</button>
        </div>
        <div class="flex gap-8" style="align-items:center">
            <input type="date" class="form-control" id="rv-date-from" style="width:auto" onchange="rvDateChanged()">
            <span class="text-muted">至</span>
            <input type="date" class="form-control" id="rv-date-to" style="width:auto" onchange="rvDateChanged()">
            <button class="btn btn-small" onclick="rvResetDates()">重置</button>
        </div>
    </div>
</div>

<div class="card" style="padding:10px 16px">
    <div class="sub-tabs" id="rv-type-tabs">
        <button class="sub-tab active" data-rvtype="audit" onclick="admin_review_type('audit')">统一审核</button>
        <button class="sub-tab" data-rvtype="tpls" onclick="admin_review_type('tpls')">病历模板</button>
        <button class="sub-tab" data-rvtype="cons" onclick="admin_review_type('cons')">内容库</button>
        <button class="sub-tab" data-rvtype="maint" onclick="admin_review_type('maint')">维护申请</button>
    </div>
</div>

<div class="rv-page">
    <div class="rv-sec" data-rvsec="audit">
        <div class="rv-sec-head">统一审核（药品/设置等提交）<span class="rv-count" id="rv-audit-count"></span></div>
        <div id="rv-audit"></div>
    </div>
    <div class="rv-sec" data-rvsec="tpls" style="display:none">
        <div class="rv-sec-head">病历模板待审核<span class="rv-count" id="rv-tpls-count"></span></div>
        <div id="rv-tpls"></div>
    </div>
    <div class="rv-sec" data-rvsec="cons" style="display:none">
        <div class="rv-sec-head">内容库待审核<span class="rv-count" id="rv-cons-count"></span></div>
        <div id="rv-cons"></div>
    </div>
    <div class="rv-sec" data-rvsec="maint" style="display:none">
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
    await rvLoadAudit();
    rvRenderStats();
    rvResetDates();   // 默认近 7 天日期范围
}

/* 统一审核（audits pending：药品/设置等） */
async function rvLoadAudit() {
    const dates = rvDates();
    const q = 'status=pending&per=50' + (dates.from ? '&from=' + dates.from : '') + (dates.to ? '&to=' + dates.to : '');
    const r = await AjaxLoader.api('admin/audit?action=list&' + q, { silent: true });
    const rows = (r.data && r.data.rows) || [];
    const countEl = document.getElementById('rv-audit-count');
    const el = document.getElementById('rv-audit');
    _rvAuditLen = rows.length;
    if (countEl) countEl.textContent = rows.length ? '(' + rows.length + ')' : '';
    if (!el) return;
    if (!rows.length) { el.innerHTML = '<div style="padding:18px;color:#909399;font-size:13px">暂无待审核项</div>'; return; }
    const typeMap = { item_drug: '药品', item_lab: '检验', item_exam: '检查', drugsetting: '药品设置', template: '模板', content: '内容库', user_unlock: '账号解锁', pwd_reset: '密码重置' };
    el.innerHTML = rows.map(function (a) {
        return '<div class="rv-row" data-aid="' + a.id + '">'
            + '<div class="rv-name"><b>' + Format.escape(a.title) + '</b>'
            + '<span class="rv-prev">' + Format.escape(a.content || '') + '</span></div>'
            + '<span class="badge badge-info">' + Format.escape(typeMap[a.type] || a.type) + '</span>'
            + '<span class="rv-meta">' + Format.escape((a.proposer || '') + ' · ' + (a.created_at ? Format.datetime(a.created_at) : '')) + '</span>'
            + '<span class="rv-act"><button class="btn btn-small btn-success" data-a="ok">通过</button>'
            + '<button class="btn btn-small" data-a="no">驳回</button></span></div>';
    }).join('');
    el.querySelectorAll('.rv-row[data-aid]').forEach(function (row) {
        const aid = row.dataset.aid;
        row.querySelectorAll('button[data-a]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.dataset.a === 'ok') {
                    Dom.modal('审核通过', '<p>确认通过「' + Format.escape(row.querySelector('.rv-name b').textContent) + '」？</p>', [
                        { text: '通过', type: 'primary', onclick: async function () {
                            await AjaxLoader.api('admin/audit', { method: 'POST', data: { action: 'apply', id: aid, approve: 1 } });
                            Dom.toast('已通过', 'success');
                            rvLoadAudit(); rvRenderStats();
                        } },
                        { text: '取消', type: 'default' }
                    ]);
                    return;
                }
                Dom.modal('驳回', '<div class="form-group"><label class="form-label">驳回理由（必填）</label><input id="rv-a-reason" class="form-control" placeholder="请输入驳回理由"></div>', [
                    { text: '确认驳回', type: 'danger', onclick: async function () {
                        const reason = (document.getElementById('rv-a-reason').value || '').trim();
                        if (!reason) { Dom.toast('请填写驳回理由', 'error'); return; }
                        await AjaxLoader.api('admin/audit', { method: 'POST', data: { action: 'apply', id: aid, approve: 0, note: reason } });
                        Dom.toast('已驳回', 'success');
                        rvLoadAudit(); rvRenderStats();
                    } },
                    { text: '取消', type: 'default' }
                ]);
            });
        });
    });
}

function admin_review_tab(t) {
    document.querySelectorAll('.sub-tab[data-rvtab]').forEach(function (b) {
        b.classList.toggle('active', b.dataset.rvtab === t);
    });
    document.querySelector('.rv-page').style.display = t === 'pending' ? '' : 'none';
    document.getElementById('rv-handled').style.display = t === 'handled' ? '' : 'none';
    if (t === 'handled') admin_review_loadHandled();
}

/* 待审类型子 tab（统一审核/模板/内容/维护申请） */
function admin_review_type(t) {
    document.querySelectorAll('#rv-type-tabs .sub-tab').forEach(function (b) {
        b.classList.toggle('active', b.dataset.rvtype === t);
    });
    document.querySelectorAll('.rv-page .rv-sec').forEach(function (el) {
        el.style.display = el.dataset.rvsec === t ? '' : 'none';
    });
}

/* 日期范围筛选（默认近 7 天；跨度 ≤ 1 个月） */
function rvDates() {
    const d = document.getElementById('rv-date-from').value, t = document.getElementById('rv-date-to').value;
    return { from: d, to: t };
}

function rvDateChanged() {
    const d = rvDates();
    if (d.from && d.to) {
        const span = Math.round((new Date(d.to) - new Date(d.from)) / 86400000);
        if (span > 31) { Dom.toast('日期跨度不能超过 1 个月', 'error'); document.getElementById('rv-date-to').value = ''; return; }
    }
    rvReload();
}

function rvResetDates() {
    const to = new Date();
    const from = new Date(to.getTime() - 6 * 86400000);
    document.getElementById('rv-date-from').value = from.toISOString().slice(0, 10);
    document.getElementById('rv-date-to').value = to.toISOString().slice(0, 10);
    rvReload();
}

function rvReload() {
    if (_rvKind) {
        rvLoad('tpls', 'rv-tpls', 'emr/template-list?review=1', '模板');
        rvLoad('cons', 'rv-cons', 'emr/content-list?review=1', '内容');
        rvLoadMaint();
        rvLoadAudit();
    }
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
    const dates = rvDates();
    const q = 'status=handled&per=50' + (dates.from ? '&from=' + dates.from : '') + (dates.to ? '&to=' + dates.to : '');
    const r = await AjaxLoader.api('admin/audit?action=list&' + q, { silent: true });
    const list = (r.data && r.data.rows) || [];
    if (!list.length) { box.innerHTML = '<div class="empty-tip">暂无已处理审核记录</div>'; return; }
    const typeMap = { item_drug: '药品', item_lab: '检验', item_exam: '检查', drugsetting: '药品设置', template: '模板', content: '内容库', user_unlock: '账号解锁', pwd_reset: '密码重置' };
    const rows = list.map(function (h) {
        const statusBadge = h.status === 'approved'
            ? '<span class="badge badge-success">已通过</span>'
            : (h.status === 'rejected' ? '<span class="badge badge-danger">已驳回</span>' : '<span class="badge badge-default">' + Format.escape(h.status) + '</span>');
        const typeBadge = '<span class="badge badge-info">' + Format.escape(typeMap[h.type] || h.type) + '</span>';
        const meta = [h.proposer, h.handled_by, h.handled_at ? Format.datetime(h.handled_at) : ''].filter(Boolean).join(' · ');
        return '<div class="rv-row">'
            + '<div class="rv-name"><b>' + Format.escape(h.title) + '</b>'
            + (h.note ? '<span class="rv-prev">驳回原因：' + Format.escape(h.note) + '</span>' : '') + '</div>'
            + typeBadge + statusBadge
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