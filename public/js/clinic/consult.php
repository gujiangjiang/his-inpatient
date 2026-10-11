<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">会诊</h3>
        <p class="page-desc">会诊申请、意见回传与流程跟踪（回传后原医嘱自动标注「已会诊」）</p>
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
        <thead><tr><th>会诊ID</th><th>会诊类型</th><th>患者</th><th>申请科室</th><th>会诊科室</th><th>会诊目的</th><th>状态</th><th>申请时间</th><th>操作</th></tr></thead>
        <tbody id="consult-body"></tbody>
    </table>
    </div>
</div>

<script>
var _consultBox = 'sent';
var _consultRows = [];
var _CONSULT_TYPES = ['普通会诊', '急会诊', '多学科会诊', '院内大会诊', '院外会诊', '远程单学科会诊'];

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
            acts.push(`<button class="btn btn-small" onclick="clinic_consult_print(${x.id})">打印</button>`);
            if (_consultBox === 'received' && x.status === 'pending') {
                acts.push(`<button class="btn btn-small btn-success" onclick="clinic_consult_reply(${x.id})">回复</button>`);
            }
            if (_consultBox === 'sent' && x.status === 'pending') {
                acts.push(`<button class="btn btn-small btn-danger" onclick="clinic_consult_delete(${x.id})">删除</button>`);
            }
            return `<tr>
                <td>${x.id}</td>
                <td>${Format.escape(x.consult_type || '普通会诊')}</td>
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
        const typeOpts = _CONSULT_TYPES.map(t => `<option value="${t}">${t}</option>`).join('');
        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        const nowText = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
        const overlay = Dom.modal('会诊申请', `
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">会诊类型</label>
                    <select id="consult-type" class="form-control">${typeOpts}</select>
                </div>
                <div class="form-group">
                    <label class="form-label">计划会诊时间</label>
                    <input id="consult-plan" class="form-control" data-dtpicker="datetime" value="${nowText}">
                </div>
                <div class="form-group">
                    <label class="form-label">会诊科室</label>
                    <select id="consult-dept" class="form-control"><option value="">请选择科室</option>${opts}</select>
                </div>
                <div class="form-group">
                    <label class="form-label">参加会诊科室</label>
                    <input id="consult-joins" class="form-control" placeholder="如：心血管内科（多学科时填写）">
                </div>
                <div class="form-group colspan-2">
                    <label class="form-label">病情摘要（含入院原因 / 治疗经过 / 目前化验检查结果）</label>
                    <textarea id="consult-summary" class="form-control" rows="4" placeholder="请概述病情要点">${Format.escape('1、患者因「' + (p.admission_diagnosis || '（待补充诊断）') + '」入院。')}</textarea>
                </div>
                <div class="form-group colspan-2">
                    <label class="form-label">初步诊断</label>
                    <input id="consult-diagnosis" class="form-control" value="${Format.escape(p.admission_diagnosis || '')}">
                </div>
                <div class="form-group colspan-2">
                    <label class="form-label">会诊目的</label>
                    <textarea id="consult-purpose" class="form-control" rows="3" placeholder="请填写会诊目的与需协助解决的问题"></textarea>
                </div>
            </div>`, [
            { text: '提交', type: 'primary', onclick: async function() {
                const deptId = document.getElementById('consult-dept').value;
                const purpose = (document.getElementById('consult-purpose').value || '').trim();
                if (!deptId) { Dom.toast('请选择会诊科室', 'error'); return; }
                if (!purpose) { Dom.toast('请填写会诊目的', 'error'); return; }
                const rr = await AjaxLoader.api('consult/save', { method: 'POST', data: {
                    patient_id: p.id,
                    to_dept_id: deptId,
                    purpose: purpose,
                    consult_type: document.getElementById('consult-type').value,
                    planned_at: document.getElementById('consult-plan').value,
                    summary: document.getElementById('consult-summary').value.trim(),
                    diagnosis: document.getElementById('consult-diagnosis').value.trim(),
                    join_depts: document.getElementById('consult-joins').value.trim()
                }});
                if (rr.status === 'success') {
                    Dom.toast(rr.message || '会诊申请已发送', 'success');
                    clinic_consult_tab('sent');
                }
            } },
            { text: '取消', type: 'default' }
        ]);
        if (window.DateTimePicker) DateTimePicker.initInputs(overlay);
    }).catch(() => {});
}

function clinic_consult_detail(id) {
    const x = _consultRows.find(r2 => r2.id == id);
    if (!x) return;
    const steps = [
        { name: '会诊申请', done: true, who: x.created_by_name || '', at: x.created_at || '' },
        { name: '接收申请', done: false, who: '', at: '' },
        { name: '会诊意见', done: x.status === 'replied', who: x.replied_by_name || '', at: x.replied_at || '' },
        { name: '意见接收', done: false, who: '', at: '' },
        { name: '意见确认', done: false, who: '', at: '' },
        { name: '结束会诊', done: false, who: '', at: '' }
    ];
    const flowHtml = steps.map(s => `
        <div class="consult-step${s.done ? ' done' : ''}">
            <div class="cs-dot">${s.done ? '✓' : ''}</div>
            <div class="cs-name">${s.name}</div>
            <div class="cs-who">${Format.escape(s.who)}<br>${Format.escape(s.at ? s.at.slice(0, 10) : '')}</div>
        </div>`).join('');
    const replyBlock = x.status === 'replied'
        ? `<div class="consult-reply-block"><div class="crb-title">会诊意见（${Format.escape(x.replied_by_name || '')} · ${Format.escape(x.replied_at || '')}）</div>
           <div class="consult-reply">${Format.escape(x.reply_text || '')}</div></div>`
        : '<p class="text-muted fs-13">尚未回复</p>';
    Dom.modal('会诊详情', `
        <div class="kv-grid mb-10">
            <div class="kv-item"><b>会诊类型</b><span>${Format.escape(x.consult_type || '普通会诊')}</span></div>
            <div class="kv-item"><b>申请时间</b><span>${Format.escape(x.created_at || '')}</span></div>
            <div class="kv-item"><b>会诊科室</b><span>${Format.escape(x.from_dept_name || '-')} → ${Format.escape(x.to_dept_name || '')}</span></div>
            <div class="kv-item"><b>计划会诊时间</b><span>${Format.escape(x.planned_at || '-')}</span></div>
            <div class="kv-item"><b>申请医生</b><span>${Format.escape(x.created_by_name || '')}</span></div>
            <div class="kv-item"><b>参加会诊科室</b><span>${Format.escape(x.join_depts || '-')}</span></div>
        </div>
        ${x.summary ? `<div class="card"><div class="card-header">病情摘要</div><div class="consult-purpose">${Format.escape(x.summary)}</div></div>` : ''}
        ${x.diagnosis ? `<div class="card"><div class="card-header">初步诊断</div><div class="consult-purpose">${Format.escape(x.diagnosis)}</div></div>` : ''}
        <div class="card"><div class="card-header">会诊目的</div><div class="consult-purpose">${Format.escape(x.purpose || '')}</div></div>
        ${replyBlock}
        <div class="consult-flow">${flowHtml}</div>`, [
        { text: '打印会诊单', type: 'default', onclick: () => clinic_consult_print(id) }
    ]);
}

function clinic_consult_print(id) {
    const x = _consultRows.find(r2 => r2.id == id);
    if (!x) return;
    const html = `
        <h2 class="doc-title">会诊申请单</h2>
        <div class="doc-sub">会诊ID ${x.id}</div>
        <div class="doc-meta">
            <span>患者：${Format.escape(x.patient_name || '')}</span>
            <span>床号：${Format.escape(x.bed_no || '-')}</span>
            <span>会诊类型：${Format.escape(x.consult_type || '普通会诊')}</span>
            <span>计划会诊时间：${Format.escape(x.planned_at || '-')}</span>
        </div>
        <table class="doc-table"><tbody>
            <tr><th style="width:120px">会诊科室</th><td>${Format.escape(x.to_dept_name || '')}（申请：${Format.escape(x.from_dept_name || '-')}）</td></tr>
            <tr><th>初步诊断</th><td>${Format.escape(x.diagnosis || '-')}</td></tr>
            <tr><th>病情摘要</th><td style="white-space:pre-wrap">${Format.escape(x.summary || '-')}</td></tr>
            <tr><th>会诊目的</th><td style="white-space:pre-wrap">${Format.escape(x.purpose || '')}</td></tr>
            <tr><th>会诊意见</th><td style="white-space:pre-wrap">${x.status === 'replied' ? Format.escape(x.reply_text || '') : '（待回复）'}</td></tr>
        </tbody></table>
        <div class="doc-foot">
            <span>申请医生：${Format.escape(x.created_by_name || '')} ${Format.escape(x.created_at || '')}</span>
            <span>会诊医生：${Format.escape(x.replied_by_name || '')} ${Format.escape(x.replied_at || '')}</span>
        </div>`;
    PrintHelper.printHtml('会诊申请单', html);
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
            const rr = await AjaxLoader.api('consult/reply', { method: 'POST', data: { id: id, reply_text: text } });
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
            await AjaxLoader.api('consult/delete', { method: 'POST', data: { id: id } });
            Dom.toast('已删除', 'success');
            clinic_consult_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}
</script>
