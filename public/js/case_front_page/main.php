<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <h2>住院病案首页</h2>
    <button class="btn btn-success" onclick="case_front_page_pdf()">导出 PDF</button>
</div>

<div class="card">
    <div class="card-header">患者信息</div>
    <div style="padding:15px">
        <div class="row">
            <div class="col"><div class="form-group">
                <label>住院号</label>
                <input type="text" id="cfp_admission_no" class="form-control" readonly>
            </div></div>
            <div class="col"><div class="form-group">
                <label>姓名</label>
                <input type="text" id="cfp_name" class="form-control">
            </div></div>
            <div class="col"><div class="form-group">
                <label>性别</label>
                <input type="text" id="cfp_gender" class="form-control">
            </div></div>
        </div>
        <div class="row">
            <div class="col"><div class="form-group">
                <label>年龄</label>
                <input type="text" id="cfp_age" class="form-control">
            </div></div>
            <div class="col"><div class="form-group">
                <label>入院日期</label>
                <input type="text" id="cfp_admission_date" class="form-control">
            </div></div>
            <div class="col"><div class="form-group">
                <label>科室/病区</label>
                <input type="text" id="cfp_dept_ward" class="form-control">
            </div></div>
        </div>
        <div class="row">
            <div class="col"><div class="form-group">
                <label>住址省</label>
                <input type="text" id="cfp_addr_province" class="form-control">
            </div></div>
            <div class="col"><div class="form-group">
                <label>住址市</label>
                <input type="text" id="cfp_addr_city" class="form-control">
            </div></div>
            <div class="col"><div class="form-group">
                <label>住址区</label>
                <input type="text" id="cfp_addr_district" class="form-control">
            </div></div>
        </div>
        <div class="form-group">
            <label>入院诊断</label>
            <input type="text" id="cfp_diagnosis" class="form-control">
        </div>
        <div class="form-group">
            <label>出院日期</label>
            <input type="text" id="cfp_discharge_date" class="form-control">
        </div>
        <div class="form-group">
            <label>出院诊断</label>
            <input type="text" id="cfp_discharge_diagnosis" class="form-control">
        </div>
        <div class="form-group">
            <label>门诊诊断</label>
            <input type="text" id="cfp_outpatient_diagnosis" class="form-control">
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">手术记录</div>
    <div id="surgery-records"></div>
    <button class="btn btn-small btn-primary" onclick="case_front_page_add_surgery()">添加手术</button>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">费用信息</div>
    <div id="fee-info"></div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header">编辑内容 (Quill)</div>
    <div id="case-editor"></div>
    <button class="btn btn-success" onclick="case_front_page_save()">保存</button>
</div>

<script>
var _cfpPatient = null, _cfpAdmissionNo = null, _cfpSurgeries = [];

async function case_front_page_main_init() {
    document.getElementById('main-app').style.display = 'block';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;

    // 工作站模式下优先使用全局患者上下文
    const ctx = PatientContext.current();
    if (ctx && ctx.admission_no) {
        await loadCaseFrontPage(ctx.admission_no);
        return;
    }

    // 获取当前患者
    const hash = window.location.hash.replace('#', '');
    const hashParts = hash.split('/');
    const admissionNo = hashParts.length >= 3 ? hashParts[2] : '';
    if (admissionNo) {
        await loadCaseFrontPage(admissionNo);
    } else {
        // 默认加载第一个患者
        const patients = await AjaxLoader.api('patients/list', {silent:true});
        if (patients.data && patients.data.length > 0) {
            await loadCaseFrontPage(patients.data[0].admission_no);
        }
    }
}

async function loadCaseFrontPage(admissionNo) {
    _cfpAdmissionNo = admissionNo;
    
    // 自动填充
    try {
        const autofill = await AjaxLoader.api('case/autofill?admission_no=' + admissionNo, {silent:true});
        const p = autofill.data.patient;
        _cfpPatient = p;
        
        document.getElementById('cfp_admission_no').value = p.admission_no;
        document.getElementById('cfp_name').value = p.name;
        document.getElementById('cfp_gender').value = Format.gender(p.gender);
        document.getElementById('cfp_age').value = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
        document.getElementById('cfp_admission_date').value = Format.datetime(p.admission_date);
        document.getElementById('cfp_dept_ward').value = (p.department_name || '') + '/' + (p.ward_name || '');
        document.getElementById('cfp_diagnosis').value = p.admission_diagnosis || '';
        
        // 加载已保存病案首页
        const saved = await AjaxLoader.api('case/get?admission_no=' + admissionNo, {silent:true});
        if (saved.data && saved.data.content_delta) {
            RichEditor.create('case-editor', saved.data.content_delta);
        }
        
        // 加载手术记录
        await loadSurgeries();
        
        // 加载费用
        document.getElementById('fee-info').innerHTML = '<p>总费用：' + (autofill.data.fee.total || 0) + ' 元</p>';
    } catch(e) {
        Dom.toast('加载失败', 'error');
    }
}

async function loadSurgeries() {
    const result = await AjaxLoader.api('case/surgery-list?admission_no=' + _cfpAdmissionNo, {silent:true});
    _cfpSurgeries = result.data || [];
    const container = document.getElementById('surgery-records');
    container.innerHTML = _cfpSurgeries.length ? '<table class="table"><thead><tr><th>手术</th><th>医生</th><th>麻醉</th><th>日期</th><th>愈合</th></tr></thead><tbody>' + 
    _cfpSurgeries.map(s => `<tr><td>${Format.escape(s.surgery_name)}</td><td>${Format.escape(s.surgeon_name||'')}</td><td>${Format.escape(s.anesthesia_type||'')}</td><td>${Format.datetime(s.surgery_date)}</td><td>${Format.escape(s.incision_healing||'')}</td></tr>`).join('') + '</tbody></table>' : '<p>暂无手术记录</p>';
}

function case_front_page_add_surgery() {
    const content = `
        <form id="surgery-form">
            ${Dom.formField({name:'surgery_name',label:'手术名称',type:'text'}, '')}
            ${Dom.formField({name:'anesthesia_type',label:'麻醉方式',type:'text'}, '')}
            ${Dom.formField({name:'surgery_date',label:'手术日期',type:'datetime'}, new Date().toISOString().slice(0,16))}
            ${Dom.formField({name:'incision_healing',label:'切口愈合',type:'text'}, '')}
            ${Dom.formField({name:'complications',label:'并发症',type:'textarea'}, '')}
            <input type="hidden" name="patient_id" value="${_cfpPatient ? _cfpPatient.id : ''}">
            <input type="hidden" name="admission_no" value="${_cfpAdmissionNo}">
        </form>
    `;
    Dom.modal('添加手术', content, [
        {text:'保存',type:'primary',onclick:async function(){
            const form = document.getElementById('surgery-form');
            const data = {};
            new FormData(form).forEach((v,k)=>data[k]=v);
            await AjaxLoader.api('case/surgery-save', {method:'POST',data});
            loadSurgeries();
        }},
        {text:'取消',type:'default'}
    ]);
}

async function case_front_page_save() {
    const editor = RichEditor.create('case-editor');
    const delta = editor.getDelta();
    if (!delta || delta === '{}') { Dom.toast('请输入内容','error'); return; }
    
    await AjaxLoader.api('case/save', {method:'POST',data:{
        admission_no: _cfpAdmissionNo,
        content_delta: delta,
        content_html: editor.getHTML()
    }});
    Dom.toast('保存成功','success');
}

function case_front_page_pdf() {
    const editor = RichEditor.create('case-editor');
    if (typeof html2pdf !== 'undefined') {
        html2pdf().set({margin:1, filename:'病案首页.pdf', html2canvas:{scale:2}, jsPDF:{unit:'in',format:'letter'}}).from(document.getElementById('case-editor')).save();
    } else {
        window.print();
    }
}

function navigateTo(m,p){ AjaxLoader.loadPage(m,p); }
</script>
