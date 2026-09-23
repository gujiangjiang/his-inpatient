<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>患者管理</h2>
    <button class="btn btn-primary" onclick="patients_new()">入院登记</button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr><th>住院号</th><th>姓名</th><th>性别</th><th>年龄</th><th>科室</th><th>病区</th><th>床号</th><th>入院日期</th><th>诊断</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody id="patient-table-body"></tbody>
    </table>
</div>

<script>
let _patientsData = [], _deptList = [], _wardList = [];

async function patients_patient_list_init() {
    document.getElementById('main-app').style.display = 'block';
    document.getElementById('login-container').style.display = 'none';
    const user = Auth.user;
    const sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;

    const [patientsRes, deptsRes, wardsRes] = await Promise.all([
        AjaxLoader.api('patients/list', {silent:true}),
        AjaxLoader.api('admin/department-list', {silent:true}),
        AjaxLoader.api('admin/ward-list', {silent:true})
    ]);
    _patientsData = patientsRes.data || [];
    _deptList = deptsRes.data || [];
    _wardList = wardsRes.data || [];

    const tbody = document.getElementById('patient-table-body');
    tbody.innerHTML = '';
    _patientsData.forEach(p => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${Format.escape(p.admission_no)}</td>
            <td>${Format.escape(p.name)}</td>
            <td>${Format.gender(p.gender)}</td>
            <td>${p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : ''}</td>
            <td>${Format.escape(p.department_name || '')}</td>
            <td>${Format.escape(p.ward_name || '')}</td>
            <td>${Format.escape(p.bed_no || '')}</td>
            <td>${Format.datetime(p.admission_date)}</td>
            <td>${Format.escape(p.admission_diagnosis || '')}</td>
            <td>${p.status === 'active' ? '<span class="badge badge-success">在院</span>' : '<span class="badge badge-default">出院</span>'}</td>
            <td>
                <button class="btn btn-small" onclick="patients_edit(${p.id})">编辑</button>
                ${p.status === 'active' ? `<button class="btn btn-small btn-danger" onclick="patients_discharge(${p.id})">出院</button>` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function patients_new() {
    showPatientForm({});
}

function patients_edit(id) {
    const p = _patientsData.find(p => p.id == id);
    if (p) showPatientForm(p);
}

function patients_discharge(id) {
    Dom.modal('确认出院', '<p>确认患者出院吗？</p>', [
        {text:'确认',type:'danger',onclick:async function(){
            await AjaxLoader.api('patients/discharge', {method:'POST',data:{id}});
            patients_patient_list_init();
        }},
        {text:'取消',type:'default'}
    ]);
}

function showPatientForm(p) {
    let deptHtml = '<option value="">请选择</option>';
    _deptList.forEach(d => { deptHtml += `<option value="${d.id}" ${p.department_id==d.id?'selected':''}>${Format.escape(d.name)}</option>`; });
    let wardHtml = '<option value="">请选择</option>';
    _wardList.forEach(w => { wardHtml += `<option value="${w.id}" ${p.ward_id==w.id?'selected':''}>${Format.escape(w.name)}</option>`; });

    const content = `
        <form id="patient-form" style="max-height:70vh;overflow-y:auto">
            ${Dom.formField({name:'name',label:'姓名',type:'text'}, p.name||'')}
            ${Dom.formField({name:'gender',label:'性别',type:'select',options:[{label:'男',value:'male'},{label:'女',value:'female'}]}, p.gender||'')}
            ${Dom.formField({name:'birth_date',label:'生日',type:'date'}, p.birth_date||'')}
            ${Dom.formField({name:'id_card',label:'身份证号',type:'text'}, p.id_card||'')}
            ${Dom.formField({name:'admission_diagnosis',label:'入院诊断',type:'text'}, p.admission_diagnosis||'')}
            <input type="hidden" name="admission_icd_code" id="admission_icd_code" value="${Format.escape(p.admission_icd_code||'')}">
            <div class="form-group">
                <label class="form-label">科室</label>
                <select name="department_id" class="form-control">${deptHtml}</select>
            </div>
            <div class="form-group">
                <label class="form-label">病区</label>
                <select name="ward_id" class="form-control">${wardHtml}</select>
            </div>
            ${Dom.formField({name:'bed_no',label:'床号',type:'text'}, p.bed_no||'')}
            ${Dom.formField({name:'admission_date',label:'入院日期',type:'datetime'}, p.admission_date || new Date().toISOString().slice(0,16))}
            <input type="hidden" name="id" value="${p.id || ''}">
        </form>
    `;
    Dom.modal('患者信息', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('patient-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            const result = await AjaxLoader.api('patients/save', {method:'POST',data});
            if (result.status === 'success') {
                Dom.toast('保存成功','success');
                patients_patient_list_init();
            }
        }},
        {text:'取消',type:'default'}
    ]);
    setTimeout(() => IcdSearch.init('admission_diagnosis', 'admission_icd_code', 'admission_diagnosis'), 100);
}

function navigateTo(module, page) {
    AjaxLoader.loadPage(module, page);
}
</script>
