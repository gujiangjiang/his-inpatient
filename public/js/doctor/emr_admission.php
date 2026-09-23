<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="flex-between mb-20">
    <div>
        <h2>入院记录</h2>
        <select id="patient-select" class="form-control" style="width:200px;display:inline-block">
            <option value="">选择患者</option>
        </select>
    </div>
    <div>
        <button class="btn btn-primary" onclick="doctor_emr_admission_save()">保存病历</button>
        <button class="btn btn-warning" onclick="doctor_emr_admission_print()">打印</button>
        <button class="btn btn-success" onclick="doctor_emr_admission_pdf()">导出 PDF</button>
    </div>
</div>

<div class="card">
    <div class="card-header">入院信息</div>
    <div style="padding:15px">
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label class="form-label">患者姓名</label>
                    <input type="text" id="patient_name" class="form-control" readonly>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label class="form-label">性别</label>
                    <input type="text" id="patient_gender" class="form-control" readonly>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label class="form-label">年龄</label>
                    <input type="text" id="patient_age" class="form-control" readonly>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label class="form-label">住院号</label>
                    <input type="text" id="admission_no" class="form-control" readonly>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label class="form-label">病区</label>
                    <input type="text" id="patient_ward" class="form-control" readonly>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label class="form-label">床号</label>
                    <input type="text" id="patient_bed" class="form-control" readonly>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">入院诊断</label>
            <div class="row">
                <div class="col">
                    <input type="text" id="admission_diagnosis" class="form-control" placeholder="选择诊断">
                    <input type="hidden" id="admission_icd_code" name="admission_icd_code">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">入院日期</label>
            <input type="text" id="admission_date" class="form-control" readonly>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        入院记录
        <button class="btn btn-small btn-success" style="float:right" onclick="TemplatePicker.select('admission', function(template) { if(template) { setEditorContent(template.content_delta, template.content_html); } })">从模板插入</button>
    </div>
    <div style="padding:15px">
        <div id="editor-container"></div>
    </div>
</div>

<script>
var currentEditor = null;
var currentPatientId = null;

async function doctor_emr_admission_init() {
    // 显示工作站
    document.getElementById('main-app').style.display = 'block';
    document.getElementById('login-container').style.display = 'none';

    // 加载sidebar
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;

    // 加载患者列表
    const patients = await AjaxLoader.api('patients/list', { silent: true });
    const sel = document.getElementById('patient-select');
    sel.innerHTML = '<option value="">选择患者</option>';
    patients.data.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name + ' (' + p.admission_no + ')';
        sel.appendChild(opt);
    });

    sel.addEventListener('change', function() {
        if (this.value) {
            loadPatientInfo(this.value);
        }
    });

    // ICD-10 搜索初始化
    IcdSearch.init('admission_diagnosis', 'admission_icd_code');
}

async function loadPatientInfo(patientId) {
    currentPatientId = patientId;
    const result = await AjaxLoader.api('patients/get?id=' + patientId);
    const p = result.data;
    document.getElementById('patient_name').value = p.name;
    document.getElementById('patient_gender').value = Format.gender(p.gender);
    document.getElementById('patient_age').value = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
    document.getElementById('admission_no').value = p.admission_no;
    document.getElementById('patient_ward').value = p.ward_name || '';
    document.getElementById('patient_bed').value = p.bed_no || '';
    document.getElementById('admission_date').value = Format.datetime(p.admission_date);
    document.getElementById('admission_diagnosis').value = p.admission_diagnosis || '';
    document.getElementById('admission_icd_code').value = p.admission_icd_code || '';
}

function setEditorContent(delta, html) {
    RichEditor.create('editor-container', delta);
}

async function doctor_emr_admission_save() {
    if (!currentPatientId) { Dom.toast('请选择患者', 'error'); return; }
    const editor = RichEditor.create('editor-container');
    const delta = editor.getDelta();
    const html = editor.getHTML();
    if (!html || html === '<p><br></p>' || delta === '{}') { Dom.toast('请输入内容', 'error'); return; }
    await AjaxLoader.api('emr/save', {
        method: 'POST',
        data: {
            patient_id: currentPatientId,
            record_type: 'admission',
            content_delta: delta,
            content_html: html,
            diagnosis: document.getElementById('admission_diagnosis').value,
            icd_code: document.getElementById('admission_icd_code').value
        }
    });
    Dom.toast('保存成功', 'success');
}

function doctor_emr_admission_print() {
    PrintHelper.print('editor-container');
}

function doctor_emr_admission_pdf() {
    PdfGenerator.generate('editor-container', '入院记录.pdf');
}

function navigateTo(module, page) {
    AjaxLoader.loadPage(module, page);
}
</script>
