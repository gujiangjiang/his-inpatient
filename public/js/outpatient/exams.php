<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>检查报告 <span class="page-sub">来源：门诊一体化系统 · 影像由 PACS 提供</span></h2>
    <button class="btn" onclick="outpatient_exams_load()">刷新</button>
</div>

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

<script>
var _opdExamRows = [];
var _OPD_EXAM_STATUS = { final: '已发布', registered: '已登记', preliminary: '初步', amended: '已修订', corrected: '已纠正', cancelled: '已取消' };

async function outpatient_exams_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    outpatient_exams_load();
}

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
