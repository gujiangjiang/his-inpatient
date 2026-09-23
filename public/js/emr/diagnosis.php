<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="card">
    <div class="card-header">诊断维护</div>
    <div class="diag-form" style="padding:15px">
        <div class="row">
            <div class="col">
                <div class="form-group">
                    <label class="form-label">患者</label>
                    <input type="text" id="diag-patient" class="form-control" readonly>
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label class="form-label">入院诊断</label>
                    <input type="text" id="diag-admission" class="form-control" placeholder="选择或输入诊断">
                </div>
            </div>
            <div class="col">
                <div class="form-group">
                    <label class="form-label">ICD-10 编码</label>
                    <input type="text" id="diag-icd" class="form-control" readonly>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">门诊诊断 / 补充诊断</label>
            <textarea id="diag-extra" class="form-control" rows="3"></textarea>
        </div>
        <button class="btn btn-success" onclick="emr_diagnosis_save()">保存诊断</button>
    </div>
</div>

<script>
var _diagPatient = null;

/**
 * 诊断页初始化: 使用全局患者上下文
 */
async function emr_diagnosis_init() {
    _diagPatient = PatientContext.current();
    if (!_diagPatient) { Dom.toast('请先选择患者', 'error'); return; }
    document.getElementById('diag-patient').value = _diagPatient.name + ' (' + (_diagPatient.admission_no || '') + ')';
    document.getElementById('diag-admission').value = _diagPatient.admission_diagnosis || '';
    document.getElementById('diag-icd').value = _diagPatient.admission_icd_code || '';
    IcdSearch.init('diag-admission', 'diag-icd');
}

/**
 * 保存入院诊断
 */
async function emr_diagnosis_save() {
    const diag = document.getElementById('diag-admission').value;
    const icd = document.getElementById('diag-icd').value;
    if (!diag) { Dom.toast('请输入诊断', 'error'); return; }
    const r = await AjaxLoader.api('patients/save', {
        method: 'POST',
        data: {
            id: _diagPatient.id,
            name: _diagPatient.name,
            gender: _diagPatient.gender,
            birth_date: _diagPatient.birth_date,
            department_id: _diagPatient.department_id,
            ward_id: _diagPatient.ward_id,
            admission_diagnosis: diag,
            admission_icd_code: icd
        }
    });
    _diagPatient.admission_diagnosis = diag;
    _diagPatient.admission_icd_code = icd;
    PatientContext.set(_diagPatient);
    Dom.toast('诊断已保存', 'success');
}
</script>