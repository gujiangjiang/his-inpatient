<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>就诊记录 <span class="page-sub">住院信息 + 门诊一体化记录</span></h2>
    <button class="btn" onclick="outpatient_records_reload()">刷新</button>
</div>

<div class="tabs">
    <button class="tab active" id="opd-tab-opd" onclick="outpatient_records_tab('opd')">门诊记录</button>
    <button class="tab" id="opd-tab-his" onclick="outpatient_records_tab('his')">住院信息</button>
</div>

<div id="opd-pane-opd">
    <div class="card">
        <div class="card-header">患者匹配</div>
        <div id="opd-match" class="fs-13 text-muted">加载中…</div>
    </div>
    <div class="card">
        <div class="card-header">门诊就诊</div>
        <table class="table">
            <thead><tr><th>就诊号</th><th>科室</th><th>挂号时间</th><th>状态</th><th>转归</th></tr></thead>
            <tbody id="opd-visits-body"></tbody>
        </table>
    </div>
    <div class="card">
        <div class="card-header">门诊诊断</div>
        <table class="table">
            <thead><tr><th>时间</th><th>诊断</th><th>ICD-10</th></tr></thead>
            <tbody id="opd-conditions-body"></tbody>
        </table>
    </div>
    <div class="card">
        <div class="card-header">门诊处方</div>
        <table class="table">
            <thead><tr><th>时间</th><th>药品</th><th>用法</th><th>数量</th><th>状态</th></tr></thead>
            <tbody id="opd-prescriptions-body"></tbody>
        </table>
    </div>
</div>

<div id="opd-pane-his" class="hidden">
    <div class="card">
        <div class="card-header">住院信息</div>
        <div id="opd-his-info" class="kv-grid"></div>
    </div>
    <div class="card">
        <div class="card-header">出院记录</div>
        <table class="table">
            <thead><tr><th>出院时间</th><th>出院诊断</th><th>ICD-10</th><th>出院医嘱</th></tr></thead>
            <tbody id="opd-discharges-body"></tbody>
        </table>
    </div>
</div>

<script>
var _OPD_VISIT_STATUS = { pending: '待缴费', paid: '待就诊', visiting: '就诊中', finished: '已诊毕', refunded: '已退费', cancelled: '已取消' };
var _OPD_RX_STATUS = { active: '进行中', completed: '已完成', cancelled: '已取消' };

async function outpatient_records_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    outpatient_records_reload();
}

function outpatient_records_tab(name) {
    document.getElementById('opd-pane-opd').classList.toggle('hidden', name !== 'opd');
    document.getElementById('opd-pane-his').classList.toggle('hidden', name !== 'his');
    document.getElementById('opd-tab-opd').classList.toggle('active', name === 'opd');
    document.getElementById('opd-tab-his').classList.toggle('active', name === 'his');
}

function outpatient_records_reload() {
    const p = PatientContext.current();
    if (!p) return;
    AjaxLoader.api('outpatient/records?patient_id=' + p.id, { silent: true }).then(r => {
        const d = r.data;
        outpatient_records_match(d.opd);
        outpatient_records_visits(d.opd);
        outpatient_records_simple('opd-conditions-body', d.opd.conditions, c =>
            `<td>${Format.escape(c.date)}</td><td>${Format.escape(c.name)}</td><td>${Format.escape(c.icd)}</td>`, 3, '暂无门诊诊断');
        outpatient_records_simple('opd-prescriptions-body', d.opd.prescriptions, x =>
            `<td>${Format.escape(x.date)}</td><td>${Format.escape(x.name)}</td><td>${Format.escape(x.dosage)}</td><td>${Format.escape(x.qty)}</td><td>${Format.escape(_OPD_RX_STATUS[x.status] || x.status)}</td>`, 5, '暂无门诊处方');
        outpatient_records_his(d);
    }).catch(() => {});
}

function outpatient_records_match(opd) {
    const el = document.getElementById('opd-match');
    if (!opd.enabled) {
        el.innerHTML = '门诊接口未启用，请管理员在「接口管理」中配置';
    } else if (opd.error) {
        el.innerHTML = '<span class="badge badge-danger">接口异常</span> ' + Format.escape(opd.error);
    } else if (!opd.matched) {
        el.innerHTML = '<span class="badge badge-warning">未匹配</span> 按身份证未在门诊系统找到该患者';
    } else {
        el.innerHTML = '<span class="badge badge-success">已匹配</span> 门诊号 ' + Format.escape(opd.patient_no) +
            ' · 就诊 ' + opd.visits.length + ' 次 · 诊断 ' + opd.conditions.length + ' 条 · 处方 ' + opd.prescriptions.length + ' 张';
    }
}

function outpatient_records_visits(opd) {
    outpatient_records_simple('opd-visits-body', opd.visits, v => {
        const dispo = v.disposition ? `<span class="badge badge-default">${Format.escape(v.disposition)}</span>` +
            (v.disposition_detail ? ' ' + Format.escape(v.disposition_detail) : '') : '-';
        return `<td>${Format.escape(v.flow_no)}</td><td>${Format.escape(v.dept)}</td><td>${Format.escape(v.date)}</td>` +
            `<td>${Format.escape(_OPD_VISIT_STATUS[v.status] || v.status)}</td><td>${dispo}</td>`;
    }, 5, '暂无门诊就诊记录');
}

function outpatient_records_simple(tbodyId, rows, cellFn, colspan, emptyText) {
    const tbody = document.getElementById(tbodyId);
    rows = rows || [];
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="${colspan}"><div class="empty-state">${Format.escape(emptyText)}</div></td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>${cellFn(r)}</tr>`).join('');
}

function outpatient_records_his(d) {
    const h = d.his;
    const items = [
        ['姓名', h.name], ['性别', Format.gender(h.gender)], ['出生日期', h.birth_date],
        ['身份证号', h.id_card], ['住院号', h.admission_no], ['患者号', h.patient_no],
        ['入院时间', h.admission_date], ['科室 / 病区 / 床号', [h.department, h.ward, h.bed_no].filter(Boolean).join(' / ')],
        ['费别', h.fee_type], ['入院诊断', h.admission_diagnosis], ['状态', h.status === 'active' ? '在院' : '已出院'],
    ];
    document.getElementById('opd-his-info').innerHTML = items.map(([k, v]) =>
        `<div class="kv-item"><b>${k}</b><span>${Format.escape(v || '-')}</span></div>`).join('');
    outpatient_records_simple('opd-discharges-body', d.discharges, r =>
        `<td>${Format.escape(Format.date(r.discharged_at))}</td><td>${Format.escape(r.discharge_diagnosis || '')}</td>` +
        `<td>${Format.escape(r.discharge_icd_code || '')}</td><td>${Format.escape(r.discharge_advice || '')}</td>`,
        4, '暂无出院记录');
}
</script>
