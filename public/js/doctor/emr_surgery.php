<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>手术记录</h2>
    <select id="patient-select" class="form-control" style="width:200px" onchange="loadPatientForSurgery(this.value)">
        <option value="">选择患者</option>
    </select>
    <button class="btn btn-success" onclick="doctor_emr_surgery_save()">保存手术记录</button>
</div>

<div class="card">
    <div class="card-header">
        手术记录
        <button class="btn btn-short btn-success" style="float:right" onclick="TemplatePicker.select('surgery', function(t) { if(t) { RichEditor.create('editor-container', t.content_delta); } })">从模板插入</button>
    </div>
    <div style="padding:15px">
        <div id="editor-container"></div>
    </div>
</div>

<script>
var _surgeryPatient = null, _patientsCache4 = [];
async function doctor_emr_surgery_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    const result = await AjaxLoader.api('patients/list', {silent:true});
    _patientsCache4 = result.data || [];
    const sel = document.getElementById('patient-select');
    _patientsCache4.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + ' (' + p.admission_no + ')';
        sel.appendChild(opt);
    });
}
function loadPatientForSurgery(id){ _surgeryPatient = _patientsCache4.find(p=>p.id==id); }
async function doctor_emr_surgery_save() {
    if(!_surgeryPatient){ Dom.toast('请选择患者','error'); return; }
    const e = RichEditor.create('editor-container');
    const delta = e.getDelta();
    if(!delta||delta==='{}'){ Dom.toast('请输入内容','error'); return; }
    await AjaxLoader.api('emr/save',{method:'POST',data:{patient_id:_surgeryPatient.id,record_type:'surgery',content_delta:delta,content_html:e.getHTML()}});
    Dom.toast('保存成功','success');
}
function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
