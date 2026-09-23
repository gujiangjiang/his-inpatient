<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>生命体征</h2>
    <button class="btn btn-primary" onclick="nurse_vitals_new()">录入</button>
</div>

<div class="card">
    <select id="vitals-patient" class="form-control" onchange="loadVitals(this.value)">
        <option value="">选择患者</option>
    </select>
</div>

<div class="card" id="vitals-table-container">
    <table class="table">
        <thead>
            <tr><th>时间</th><th>体温</th><th>脉搏</th><th>收缩压</th><th>舒张压</th><th>呼吸</th><th>血氧</th><th>身高</th><th>体重</th><th>备注</th></tr>
        </thead>
        <tbody id="vitals-table-body"></tbody>
    </table>
</div>

<script>
var _vitalsPatients = [];
async function nurse_vitals_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_nurse.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    const result = await AjaxLoader.api('nurse/patient-list', {silent:true});
    _vitalsPatients = result.data || [];
    const sel = document.getElementById('vitals-patient');
    _vitalsPatients.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + ' (' + p.bed_no + ')';
        sel.appendChild(opt);
    });
}

async function loadVitals(patientId) {
    if (!patientId) return;
    const result = await AjaxLoader.api('nurse/vitals-list?patient_id=' + patientId, {silent:true});
    const tbody = document.getElementById('vitals-table-body');
    tbody.innerHTML = '';
    (result.data || []).forEach(v => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.datetime(v.recorded_at)}</td>
            <td>${v.temperature}</td><td>${v.pulse}</td>
            <td>${v.blood_pressure_systolic}</td><td>${v.blood_pressure_diastolic}</td>
            <td>${v.respiratory_rate}</td><td>${v.oxygen_saturation}</td>
            <td>${v.height}</td><td>${v.weight}</td><td>${Format.escape(v.notes||'')}</td>
        `;
        tbody.appendChild(tr);
    });
}

function nurse_vitals_new() {
    const pid = document.getElementById('vitals-patient').value;
    if (!pid) { Dom.toast('请选择患者','error'); return; }
    const content = `
        <form id="vitals-form">
            ${Dom.formField({name:'temperature',label:'体温(℃)',type:'text'}, '')}
            ${Dom.formField({name:'pulse',label:'脉搏(次/分)',type:'text'}, '')}
            ${Dom.formField({name:'blood_pressure_systolic',label:'收缩压',type:'text'}, '')}
            ${Dom.formField({name:'blood_pressure_diastolic',label:'舒张压',type:'text'}, '')}
            ${Dom.formField({name:'respiratory_rate',label:'呼吸(次/分)',type:'text'}, '')}
            ${Dom.formField({name:'oxygen_saturation',label:'血氧(%)',type:'text'}, '')}
            ${Dom.formField({name:'height',label:'身高(cm)',type:'text'}, '')}
            ${Dom.formField({name:'weight',label:'体重(kg)',type:'text'}, '')}
            ${Dom.formField({name:'notes',label:'备注',type:'textarea'}, '')}
            <input type="hidden" name="patient_id" value="${pid}">
        </form>
    `;
    Dom.modal('录入生命体征', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('vitals-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            await AjaxLoader.api('nurse/vitals-save', {method:'POST',data});
            loadVitals(pid);
        }},
        {text:'取消',type:'default'}
    ]);
}

function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
