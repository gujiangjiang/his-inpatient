<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">检验</h3>
        <p class="page-desc">检验申请（生成医嘱）与门诊检验结果</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="LabPanel.open()">检验申请</button>
        <button class="btn" onclick="outpatient_labs_printAll()">全部打印</button>
        <button class="btn" onclick="outpatient_labs_reload()">刷新</button>
    </div>
</div>

<div class="tabs">
    <button class="tab active" id="lab-tab-apply" onclick="outpatient_labs_tab('apply')">检验申请</button>
    <button class="tab" id="lab-tab-opd" onclick="outpatient_labs_tab('opd')">门诊结果</button>
</div>

<div id="lab-pane-apply">
    <div class="card">
        <p class="apply-warn" style="font-size:12px">请一次性开好所有的项目！</p>
        <div id="lab-apply-notice"></div>
        <div style="overflow-x:auto">
        <table class="table hidden" id="lab-apply-table">
            <thead><tr><th>次</th><th>申请单号</th><th>申请项目</th><th>标本</th><th>执行科室</th><th>申请时间</th><th>状态</th><th>操作</th></tr></thead>
            <tbody id="lab-apply-body"></tbody>
        </table>
        </div>
    </div>
</div>

<div id="lab-pane-opd" class="hidden">
    <div class="card">
        <div class="card-header">门诊检验 <span id="opd-labs-match" class="fs-12 text-muted"></span></div>
        <div id="opd-labs-notice"></div>
        <div style="overflow-x:auto">
        <table class="table hidden" id="opd-labs-table">
            <thead><tr><th>时间</th><th>项目</th><th>结果</th><th>单位</th><th>参考范围</th><th>提示</th></tr></thead>
            <tbody id="opd-labs-body"></tbody>
        </table>
        </div>
    </div>
</div>

<script>
var _labApplyRows = [];
var _LAB_STATUS = { pending: '待采集', collected: '已采集', reported: '已报告' };
var _LAB_STATUS_CLS = { pending: 'badge-pending', collected: 'badge-info', reported: 'badge-executing' };

async function outpatient_labs_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    outpatient_labs_reload();
}

function outpatient_labs_tab(name) {
    document.getElementById('lab-pane-apply').classList.toggle('hidden', name !== 'apply');
    document.getElementById('lab-pane-opd').classList.toggle('hidden', name !== 'opd');
    document.getElementById('lab-tab-apply').classList.toggle('active', name === 'apply');
    document.getElementById('lab-tab-opd').classList.toggle('active', name === 'opd');
}

function outpatient_labs_reload() {
    outpatient_labs_loadApply();
    outpatient_labs_load();
}

/* ===== 检验申请 (本系统) ===== */
function outpatient_labs_loadApply() {
    const p = PatientContext.current();
    if (!p) return;
    AjaxLoader.api('lab/list?patient_id=' + p.id, { silent: true }).then(r => {
        _labApplyRows = r.data || [];
        const notice = document.getElementById('lab-apply-notice');
        const table = document.getElementById('lab-apply-table');
        if (!_labApplyRows.length) {
            notice.innerHTML = '<div class="empty-state">暂无检验申请，点击右上角「检验申请」开具</div>';
            table.classList.add('hidden');
            return;
        }
        notice.innerHTML = '';
        table.classList.remove('hidden');
        document.getElementById('lab-apply-body').innerHTML = _labApplyRows.map((x, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${Format.escape(x.report_no)}</td>
                <td>${Format.escape(x.exam_name)}</td>
                <td>${Format.escape(x.specimen_type || '')}</td>
                <td>${Format.escape(x.exec_dept || '')}</td>
                <td>${Format.escape(x.created_at || '')}</td>
                <td><span class="badge ${_LAB_STATUS_CLS[x.status] || 'badge-default'}">${_LAB_STATUS[x.status] || Format.escape(x.status)}</span>${x.is_critical == 1 ? ' <span class="badge badge-critical">危急</span>' : ''}</td>
                <td>
                    <button class="btn btn-small" onclick="outpatient_labs_detail(${x.id})">查看</button>
                    <button class="btn btn-small" onclick="outpatient_labs_print(${x.id})">打印</button>
                    ${x.status === 'pending' ? `<button class="btn btn-small btn-danger" onclick="outpatient_labs_delete(${x.id})">删除</button>` : ''}
                </td>
            </tr>`).join('');
    }).catch(() => {});
}

function outpatient_labs_detail(id) {
    AjaxLoader.api('lab/detail?id=' + id, { silent: true }).then(r => {
        const rep = r.data;
        const rows = (rep.items || []).map((it, i) => `<tr>
            <td>${i + 1}</td><td>${Format.escape(it.item_name)}</td>
            <td>${Format.escape(it.result || '')}</td><td>${Format.escape(it.unit || '')}</td>
            <td>${Format.escape(it.reference_range || '')}</td>
            <td>${it.flag ? Format.labFlag(it.flag) : ''}</td></tr>`).join('');
        Dom.modal('检验详情', `
            <div class="kv-grid mb-10">
                <div class="kv-item"><b>申请单号</b><span>${Format.escape(rep.report_no)}</span></div>
                <div class="kv-item"><b>状态</b><span>${_LAB_STATUS[rep.status] || Format.escape(rep.status)}</span></div>
                <div class="kv-item"><b>标本</b><span>${Format.escape(rep.specimen_type || '')}</span></div>
                <div class="kv-item"><b>开单时间</b><span>${Format.escape(rep.created_at || '')}</span></div>
            </div>
            <table class="table"><thead><tr><th>#</th><th>项目</th><th>结果</th><th>单位</th><th>参考范围</th><th>标记</th></tr></thead><tbody>${rows}</tbody></table>`);
    }).catch(() => {});
}

function outpatient_labs_printBlock(rep) {
    const rows = (rep.items || []).map((it, i) => `<tr><td>${i + 1}</td><td>${Format.escape(it.item_name)}</td>
        <td>${Format.escape(rep.specimen_type || '')}</td><td>${Format.escape(it.result || '')}</td><td>${Format.escape(it.reference_range || '')}</td></tr>`).join('');
    return `
        <h2 class="doc-title">检验申请单</h2>
        <div class="doc-sub">${Format.escape(rep.report_no)}</div>
        <div class="doc-meta"><span>患者：${Format.escape(rep.patient_name || '')}</span><span>床号：${Format.escape(rep.bed_no || '-')}</span><span>开单时间：${Format.escape(rep.created_at || '')}</span></div>
        <table class="doc-table"><thead><tr><th>序号</th><th>检验项目</th><th>标本</th><th>结果</th><th>参考范围</th></tr></thead><tbody>${rows}</tbody></table>
        <div class="doc-foot"><span>开单医生：${Format.escape(rep.creator_name || '')}</span><span>打印时间：${Format.date(new Date().toISOString())}</span></div>`;
}

function outpatient_labs_print(id) {
    AjaxLoader.api('lab/detail?id=' + id, { silent: true }).then(r => {
        PrintHelper.printHtml('检验申请单', outpatient_labs_printBlock(r.data));
    }).catch(() => {});
}

function outpatient_labs_printAll() {
    if (!_labApplyRows.length) { Dom.toast('暂无检验申请', 'info'); return; }
    Promise.all(_labApplyRows.map(x => AjaxLoader.api('lab/detail?id=' + x.id, { silent: true })))
        .then(rs => {
            const html = rs.map(r => outpatient_labs_printBlock(r.data)).join('<div style="page-break-after: always;"></div>');
            PrintHelper.printHtml('检验申请单（全部）', html);
        }).catch(() => {});
}

function outpatient_labs_delete(id) {
    Dom.modal('删除检验申请', '<p>删除该检验申请？关联医嘱将标记为「作废」（已核对医嘱需先在医嘱列表处理）。</p>', [
        { text: '删除', type: 'danger', onclick: async function() {
            await AjaxLoader.api('lab/delete', { method: 'POST', data: { id } });
            Dom.toast('已删除', 'success');
            outpatient_labs_loadApply();
        } },
        { text: '取消', type: 'default' }
    ]);
}

/* ===== 门诊检验结果 (门诊一体化) ===== */
function outpatient_labs_load() {
    const p = PatientContext.current();
    if (!p) return;
    AjaxLoader.api('outpatient/labs?patient_id=' + p.id, { silent: true }).then(r => {
        const d = r.data;
        const notice = document.getElementById('opd-labs-notice');
        const table = document.getElementById('opd-labs-table');
        document.getElementById('opd-labs-match').textContent = d.matched ? ('门诊患者号 ' + d.patient_no) : '';
        notice.innerHTML = '';
        table.classList.add('hidden');

        let message = '';
        if (!d.enabled) message = '门诊接口未启用，请管理员在「接口管理」中配置';
        else if (d.error) message = '门诊接口访问失败：' + d.error;
        else if (!d.matched) message = '按身份证未在门诊系统匹配到该患者';
        else if (!d.rows.length) message = '暂无门诊检验结果';
        if (message) {
            notice.innerHTML = '<div class="empty-state">' + Format.escape(message) + '</div>';
            return;
        }

        table.classList.remove('hidden');
        document.getElementById('opd-labs-body').innerHTML = d.rows.map(row => {
            const item = row.group && row.group !== row.item ? row.group + ' · ' + row.item : row.item;
            const flag = row.flag ? `<span class="${Format.labFlagClass(row.flag)}">${Format.labFlag(row.flag)}</span>` : '';
            return `<tr>
                <td>${Format.escape(row.date)}</td>
                <td>${Format.escape(item)}</td>
                <td>${row.flag ? '<b>' + Format.escape(row.result) + '</b>' : Format.escape(row.result)}</td>
                <td>${Format.escape(row.unit)}</td>
                <td>${Format.escape(row.reference)}</td>
                <td>${flag}</td>
            </tr>`;
        }).join('');
    }).catch(() => {});
}
</script>
