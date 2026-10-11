<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>检验结果 <span class="page-sub">来源：门诊一体化系统</span></h2>
    <button class="btn" onclick="outpatient_labs_load()">刷新</button>
</div>

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

<script>
async function outpatient_labs_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    outpatient_labs_load();
}

function outpatient_labs_load() {
    const p = PatientContext.current();
    if (!p) return;
    AjaxLoader.api('outpatient/labs?patient_id=' + p.id, { silent: true }).then(r => {
        const d = r.data;
        const notice = document.getElementById('opd-labs-notice');
        const table = document.getElementById('opd-labs-table');
        document.getElementById('opd-labs-match').textContent = d.matched ? ('门诊号 ' + d.patient_no) : '';
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
