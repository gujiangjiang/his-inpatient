<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">会诊</h3>
        <p class="page-desc">会诊申请与意见回传，回传后原医嘱自动标注「已会诊」</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="clinic_consult_apply()">会诊申请</button>
        <button class="btn" onclick="clinic_consult_reload()">刷新</button>
    </div>
</div>

<div class="tabs">
    <button class="tab active" id="consult-tab-sent" onclick="clinic_consult_tab('sent')">我发起的</button>
    <button class="tab" id="consult-tab-received" onclick="clinic_consult_tab('received')">请我会诊</button>
</div>

<div class="card">
    <div id="consult-notice"></div>
    <div style="overflow-x:auto">
    <table class="table" id="consult-table">
        <thead><tr><th>患者</th><th>申请科室</th><th>目标科室</th><th>会诊目的</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
        <tbody id="consult-body"></tbody>
    </table>
    </div>
</div>

<script>
var _consultBox = 'sent';
var _consultRows = [];

async function clinic_consult_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    clinic_consult_reload();
}

function clinic_consult_tab(box) {
    _consultBox = box;
    document.getElementById('consult-tab-sent').classList.toggle('active', box === 'sent');
    document.getElementById('consult-tab-received').classList.toggle('active', box === 'received');
    clinic_consult_load();
}

function clinic_consult_reload() { clinic_consult_load(); }

function clinic_consult_load() {
    AjaxLoader.api('consult/list?box=' + _consultBox, { silent: true }).then(r => {
        _consultRows = r.data || [];
        const notice = document.getElementById('consult-notice');
        if (!_consultRows.length) {
            notice.innerHTML = '<div class="empty-state">' + (_consultBox === 'sent' ? '暂无发起的会诊' : '暂无请求本科室的会诊') + '</div>';
            document.getElementById('consult-body').innerHTML = '';
            return;
        }
        notice.innerHTML = '';
        document.getElementById('consult-body').innerHTML = _consultRows.map(x => {
            const badge = x.status === 'pending'
                ? '<span class="badge badge-pending">待回复</span>'
                : '<span class="badge badge-executing">已回复</span>';
            const acts = [];
            acts.push(`<button class="btn btn-small" onclick="clinic_consult_detail(${x.id})">详情</button>`);
            if (_consultBox === 'received' && x.status === 'pending') {
                acts.push(`<button class="btn btn-small btn-success" onclick="clinic_consult_reply(${x.id})">回复</button>`);
            }
            if (_consultBox === 'sent' && x.status === 'pending') {
                acts.push(`<button class="btn btn-small btn-danger" onclick="clinic_consult_delete(${x.id})">删除</button>`);
            }
            return `<tr>
                <td>${Format.escape(x.patient_name || '')}${x.bed_no ? ' (' + Format.escape(x.bed_no) + ')' : ''}</td>
                <td>${Format.escape(x.from_dept_name || '-')}</td>
                <td>${Format.escape(x.to_dept_name || '')}</td>
                <td class="cell-ellipsis" title="${Format.escape(x.purpose)}">${Format.escape(x.purpose)}</td>
                <td>${badge}</td>
                <td>${Format.escape(x.created_at || '')}</td>
                <td>${acts.join(' ')}</td>
            </tr>`;
        }).join('');
    }).catch(() => {});
}

function clinic_consult_apply() {
    const p = PatientContext.current();
    if (!p) { Dom.toast('请先选择患者', 'info'); return; }
    AjaxLoader.api('admin/department-list', { silent: true }).then(r => {
        const depts = (r.data || []).filter(d => d.is_active == 1);
        const opts = depts.map(d => `<option value="${d.id}">${Format.escape(d.name)}</option>`).join('');
        Dom.modal('会诊申请', `
            <div class="form-group"><label class="form-label">患者诊断</label>
                <input class="form-control" value="${Format.escape(p.admission_diagnosis || '（暂无入院诊断）')}" readonly></div>
            <div class="form-group"><label class="form-label">目标科室</label>
                <select id="consult-dept" class="form-control"><option value="">请选择科室</option>${opts}</select></div>
            <div class="form-group"><label class="form-label">会诊目的</label>
                <textarea id="consult-purpose" class="form-control" rows="3" placeholder="请填写会诊目的与病情要点"></textarea></div>`, [
            { text: '提交', type: 'primary', onclick: async function() {
                const deptId = document.getElementById('consult-dept').value;
                const purpose = (document.getElementById('consult-purpose').value || '').trim();
                if (!deptId) { Dom.toast('请选择目标科室', 'error'); return; }
                if (!purpose) { Dom.toast('请填写会诊目的', 'error'); return; }
                const rr = await AjaxLoader.api('consult/save', { method: 'POST', data: {
                    patient_id: p.id, to_dept_id: deptId, purpose
                }});
                if (rr.status === 'success') {
                    Dom.toast(rr.message || '会诊申请已发送', 'success');
                    clinic_consult_tab('sent');
                }
            } },
            { text: '取消', type: 'default' }
        ]);
    }).catch(() => {});
}

function clinic_consult_detail(id) {
    const x = _consultRows.find(r2 => r2.id == id);
    if (!x) return;
    const reply = x.status === 'replied'
        ? `<div class="card"><div class="card-header">会诊意见（${Format.escape(x.replied_by_name || '')} · ${Format.escape(x.replied_at || '')}）</div>
           <div class="consult-reply">${Format.escape(x.reply_text || '')}</div></div>`
        : '<p class="text-muted fs-13">尚未回复</p>';
    Dom.modal('会诊详情', `
        <div class="kv-grid mb-10">
            <div class="kv-item"><b>患者</b><span>${Format.escape(x.patient_name || '')}</span></div>
            <div class="kv-item"><b>会诊</b><span>${Format.escape(x.from_dept_name || '-')} → ${Format.escape(x.to_dept_name || '')}</span></div>
            <div class="kv-item"><b>发起人</b><span>${Format.escape(x.created_by_name || '')}</span></div>
            <div class="kv-item"><b>时间</b><span>${Format.escape(x.created_at || '')}</span></div>
        </div>
        <div class="card"><div class="card-header">会诊目的</div><div class="consult-purpose">${Format.escape(x.purpose || '')}</div></div>
        ${reply}`);
}

function clinic_consult_reply(id) {
    const x = _consultRows.find(r2 => r2.id == id);
    if (!x) return;
    Dom.modal('回复会诊意见', `
        <div class="consult-purpose mb-10">会诊目的：${Format.escape(x.purpose || '')}</div>
        <div class="form-group"><label class="form-label">会诊意见</label>
            <textarea id="consult-reply-text" class="form-control" rows="4" placeholder="填写会诊意见"></textarea></div>`, [
        { text: '提交回复', type: 'primary', onclick: async function() {
            const text = (document.getElementById('consult-reply-text').value || '').trim();
            if (!text) { Dom.toast('请填写会诊意见', 'error'); return; }
            const rr = await AjaxLoader.api('consult/reply', { method: 'POST', data: { id, reply_text: text } });
            if (rr.status === 'success') {
                Dom.toast(rr.message || '已回复', 'success');
                clinic_consult_load();
            }
        } },
        { text: '取消', type: 'default' }
    ]);
}

function clinic_consult_delete(id) {
    Dom.modal('删除会诊', '<p>删除该会诊申请及其关联医嘱？</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('consult/delete', { method: 'POST', data: { id } });
            Dom.toast('已删除', 'success');
            clinic_consult_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}
</script>
