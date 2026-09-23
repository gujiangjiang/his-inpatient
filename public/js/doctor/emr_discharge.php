<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>出院记录</h2>
    <select id="patient-select" class="form-control" style="width:200px" onchange="loadPatientForDischargeEMR(this.value)">
        <option value="">选择患者</option>
    </select>
    <button class="btn btn-success" onclick="doctor_emr_discharge_save()">保存出院记录</button>
</div>

<div class="card">
    <div class="card-header">
        出院记录
        <button class="btn btn-small btn-success" style="float:right" onclick="TemplatePicker.select('discharge', function(t) { if(t) { RichEditor.create('editor-container', t.content_delta); } })">从模板插入</button>
    </div>
    <div style="padding:15px">
        <div id="editor-container"></div>
    </div>
</div>

<script>
var _dischargePatient = null, _patientsCache3 = [];
async function doctor_emr_discharge_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    const result = await AjaxLoader.api('patients/list', {silent:true});
    _patientsCache3 = result.data || [];
    const sel = document.getElementById('patient-select');
    _patientsCache3.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + ' (' + p.admission_no + ')';
        sel.appendChild(opt);
    });
}
function loadPatientForDischargeEMR(id){ _dischargePatient = _patientsCache3.find(p=>p.id==id); }
async function doctor_emr_discharge_save() {
    if(!_dischargePatient){ Dom.toast('请选择患者','error'); return; }
    const e = RichEditor.create('editor-container');
    const delta = e.getDelta();
    if(!delta||delta==='{}'){ Dom.toast('请输入内容','error'); return; }
    await AjaxLoader.api('emr/save',{method:'POST',data:{patient_id:_dischargePatient.id,record_type:'discharge',content_delta:delta,content_html:e.getHTML()}});
    Dom.toast('保存成功','success');
}
function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
