<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">检查</h3>
        <p class="page-desc">检查申请（生成医嘱）与门诊检查报告 / PACS 影像</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="ExamPanel.open()">检查申请</button>
        <button class="btn" onclick="outpatient_exams_reload()">刷新</button>
    </div>
</div>

<div class="tabs">
    <button class="tab active" id="exam-tab-apply" onclick="outpatient_exams_tab('apply')">检查申请</button>
    <button class="tab" id="exam-tab-opd" onclick="outpatient_exams_tab('opd')">门诊结果</button>
</div>

<div id="exam-pane-apply">
    <div class="card">
        <div id="exam-apply-notice"></div>
        <div style="overflow-x:auto">
        <table class="table hidden" id="exam-apply-table">
            <thead><tr><th>申请单号</th><th>检查项目</th><th>部位</th><th>状态</th><th>开单时间</th><th>操作</th></tr></thead>
            <tbody id="exam-apply-body"></tbody>
        </table>
        </div>
    </div>
</div>

<div id="exam-pane-opd" class="hidden">
    <div class="card">
        <div class="card-header">门诊检查 <span id="opd-exams-match" class="fs-12 text-muted"></span></div>
        <div id="opd-exams-notice"></div>
        <div style="overflow-x:auto">
        <table class="table hidden" id="opd-exams-table">
            <thead><tr><th>时间</th><th>检查项目</th><th>开单科室</th><th>报告医生</th><th>状态</th><th>结论</th><th>操作</th></tr></thead>
            <tbody id="opd-exams-body"></tbody>
        </table>
        </div>
    </div>
</div>

<script>
var _examApplyRows = [];
var _opdExamRows = [];
var _OPD_EXAM_STATUS = { final: '已发布', registered: '已登记', preliminary: '初步', amended: '已修订', corrected: '已纠正', cancelled: '已取消' };
var _EXAM_STATUS = { pending: '待执行', reported: '已报告' };
var _EXAM_STATUS_CLS = { pending: 'badge-pending', reported: 'badge-executing' };

async function outpatient_exams_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    outpatient_exams_reload();
}

function outpatient_exams_tab(name) {
    document.getElementById('exam-pane-apply').classList.toggle('hidden', name !== 'apply');
    document.getElementById('exam-pane-opd').classList.toggle('hidden', name !== 'opd');
    document.getElementById('exam-tab-apply').classList.toggle('active', name === 'apply');
    document.getElementById('exam-tab-opd').classList.toggle('active', name === 'opd');
}

function outpatient_exams_reload() {
    outpatient_exams_loadApply();
    outpatient_exams_load();
}

/* ===== 检查申请 (本系统) ===== */
function outpatient_exams_loadApply() {
    const p = PatientContext.current();
    if (!p) return;
    AjaxLoader.api('exam/list?patient_id=' + p.id, { silent: true }).then(r => {
        _examApplyRows = r.data || [];
        const notice = document.getElementById('exam-apply-notice');
        const table = document.getElementById('exam-apply-table');
        if (!_examApplyRows.length) {
            notice.innerHTML = '<div class="empty-state">暂无检查申请，点击右上角「检查申请」开具</div>';
            table.classList.add('hidden');
            return;
        }
        notice.innerHTML = '';
        table.classList.remove('hidden');
        document.getElementById('exam-apply-body').innerHTML = _examApplyRows.map(x => `
            <tr>
                <td>${Format.escape(x.report_no)}</td>
                <td>${Format.escape(x.exam_type)}</td>
                <td>${Format.escape(x.body_part || '')}</td>
                <td><span class="badge ${_EXAM_STATUS_CLS[x.status] || 'badge-default'}">${_EXAM_STATUS[x.status] || Format.escape(x.status)}</span></td>
                <td>${Format.escape(x.created_at || '')}</td>
                <td>
                    <button class="btn btn-small" onclick="outpatient_exams_applyDetail(${x.id})">查看</button>
                    <button class="btn btn-small" onclick="outpatient_exams_applyPrint(${x.id})">打印</button>
                    ${x.status === 'pending' ? `<button class="btn btn-small btn-danger" onclick="outpatient_exams_applyDelete(${x.id})">删除</button>` : ''}
                </td>
            </tr>`).join('');
    }).catch(() => {});
}

function outpatient_exams_applyDetail(id) {
    AjaxLoader.api('exam/detail?id=' + id, { silent: true }).then(r => {
        const rep = r.data;
        Dom.modal('检查详情', `
            <div class="kv-grid mb-10">
                <div class="kv-item"><b>申请单号</b><span>${Format.escape(rep.report_no)}</span></div>
                <div class="kv-item"><b>检查项目</b><span>${Format.escape(rep.exam_type)}</span></div>
                <div class="kv-item"><b>检查部位</b><span>${Format.escape(rep.body_part || '')}</span></div>
                <div class="kv-item"><b>状态</b><span>${_EXAM_STATUS[rep.status] || Format.escape(rep.status)}</span></div>
            </div>
            ${rep.findings ? `<div class="card"><div class="card-header">所见</div><div>${Format.escape(rep.findings)}</div></div>` : ''}
            ${rep.conclusion ? `<div class="card"><div class="card-header">结论</div><div>${Format.escape(rep.conclusion)}</div></div>` : ''}
            ${rep.impression ? `<div class="card"><div class="card-header">印象</div><div>${Format.escape(rep.impression)}</div></div>` : ''}`);
    }).catch(() => {});
}

function outpatient_exams_applyPrint(id) {
    AjaxLoader.api('exam/detail?id=' + id, { silent: true }).then(r => {
        const rep = r.data;
        const html = `
            <h2 class="doc-title">检查申请单</h2>
            <div class="doc-sub">${Format.escape(rep.report_no)}</div>
            <div class="doc-meta"><span>患者：${Format.escape(rep.patient_name || '')}</span><span>床号：${Format.escape(rep.bed_no || '-')}</span><span>开单时间：${Format.escape(rep.created_at || '')}</span></div>
            <table class="doc-table"><thead><tr><th>检查项目</th><th>检查部位</th><th>所见</th><th>结论</th></tr></thead>
            <tbody><tr><td>${Format.escape(rep.exam_type)}</td><td>${Format.escape(rep.body_part || '')}</td><td>${Format.escape(rep.findings || '')}</td><td>${Format.escape(rep.conclusion || '')}</td></tr></tbody></table>
            <div class="doc-foot"><span>开单医生：${Format.escape(rep.creator_name || '')}</span><span>打印时间：${Format.date(new Date().toISOString())}</span></div>`;
        PrintHelper.printHtml('检查申请单', html);
    }).catch(() => {});
}

function outpatient_exams_applyDelete(id) {
    Dom.modal('删除检查申请', '<p>删除该检查申请？关联医嘱将标记为「作废」（已核对医嘱需先在医嘱列表处理）。</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('exam/delete', { method: 'POST', data: { id } });
            Dom.toast('已删除', 'success');
            outpatient_exams_loadApply();
        } },
        { text: '取消', type: 'default' }
    ]);
}

/* ===== 门诊检查报告 (门诊一体化) ===== */
function outpatient_exams_load() {
    const p = PatientContext.current();
    if (!p) return;
    AjaxLoader.api('outpatient/exams?patient_id=' + p.id, { silent: true }).then(r => {
        const d = r.data;
        _opdExamRows = d.rows || [];
        const notice = document.getElementById('opd-exams-notice');
        const table = document.getElementById('opd-exams-table');
        document.getElementById('opd-exams-match').textContent = d.matched ? ('门诊号 ' + d.patient_no) : '';
        notice.innerHTML = '';
        table.classList.add('hidden');

        let message = '';
        if (!d.enabled) message = '门诊接口未启用，请管理员在「接口管理」中配置';
        else if (d.error) message = '门诊接口访问失败：' + d.error;
        else if (!d.matched) message = '按身份证未在门诊系统匹配到该患者';
        else if (!_opdExamRows.length) message = '暂无门诊检查报告';
        if (message) {
            notice.innerHTML = '<div class="empty-state">' + Format.escape(message) + '</div>';
            return;
        }

        table.classList.remove('hidden');
        document.getElementById('opd-exams-body').innerHTML = _opdExamRows.map((row, i) => {
            const viewer = row.viewer_url
                ? `<button class="btn btn-small btn-primary" onclick="outpatient_exams_viewer(${i})">查看影像</button>`
                : '';
            return `<tr>
                <td>${Format.escape(row.date)}</td>
                <td>${Format.escape(row.item)}${row.modality ? ' <span class="badge badge-default">' + Format.escape(row.modality) + '</span>' : ''}</td>
                <td>${Format.escape(row.dept)}</td>
                <td>${Format.escape(row.doctor)}</td>
                <td>${Format.escape(_OPD_EXAM_STATUS[row.status] || row.status)}</td>
                <td class="cell-ellipsis" title="${Format.escape(row.conclusion)}">${Format.escape(row.conclusion)}</td>
                <td>
                    <button class="btn btn-small" onclick="outpatient_exams_detail(${i})">详情</button>
                    ${viewer}
                </td>
            </tr>`;
        }).join('');
    }).catch(() => {});
}

function outpatient_exams_viewer(idx) {
    const row = _opdExamRows[idx];
    if (row && row.viewer_url) window.open(row.viewer_url, '_blank');
}

function outpatient_exams_detail(idx) {
    const row = _opdExamRows[idx];
    if (!row) return;
    const viewer = row.viewer_url
        ? `<button class="btn btn-primary" onclick="outpatient_exams_viewer(${idx})">查看影像</button>` : '';
    const content = `
        <div class="kv-grid mb-10">
            <div class="kv-item"><b>检查项目</b><span>${Format.escape(row.item)}</span></div>
            <div class="kv-item"><b>报告号</b><span>${Format.escape(row.report_no)}</span></div>
            <div class="kv-item"><b>检查号</b><span>${Format.escape(row.order_no)}</span></div>
            <div class="kv-item"><b>报告时间</b><span>${Format.escape(row.date)}</span></div>
            <div class="kv-item"><b>开单科室</b><span>${Format.escape(row.dept || '-')}</span></div>
            <div class="kv-item"><b>报告医生</b><span>${Format.escape(row.doctor || '-')}</span></div>
        </div>
        <div class="card"><div class="card-header">影像所见</div><div>${Format.escape(row.findings || '无')}</div></div>
        <div class="card"><div class="card-header">影像结论</div><div>${Format.escape(row.conclusion || '无')}</div></div>
    `;
    Dom.modal('检查报告详情', content, viewer ? [{ text: '查看影像', type: 'primary', onclick: () => outpatient_exams_viewer(idx) }] : []);
}
</script>
