<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="page-head flex-between">
    <div>
        <h3 class="page-title">三级医师</h3>
        <p class="page-desc">住院医师 / 主治医师 / 主任医师与诊疗组设置</p>
    </div>
    <button class="btn" onclick="clinic_team_load()">刷新</button>
</div>

<div class="card" style="max-width:600px">
    <div class="form-group">
        <label class="form-label">住院医师（主管医师）</label>
        <select id="team-resident" class="form-control"></select>
    </div>
    <div class="form-group">
        <label class="form-label">主治医师（上级医师）</label>
        <select id="team-attending" class="form-control"></select>
    </div>
    <div class="form-group">
        <label class="form-label">主任医师</label>
        <select id="team-director" class="form-control"></select>
    </div>
    <div class="form-group">
        <label class="form-label">诊疗组</label>
        <input id="team-group" class="form-control" placeholder="如：骨科一组">
    </div>
    <button class="btn btn-primary" onclick="clinic_team_save()">保存</button>
</div>

<script>
var _teamDoctors = null;

async function clinic_team_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    const sidebar = await fetch('/js/shared/sidebar_doctor.php').then(r => r.text());
    document.getElementById('sidebar').innerHTML = sidebar;
    clinic_team_load();
}

async function clinic_team_load() {
    const p = PatientContext.current();
    if (!p) return;
    if (!_teamDoctors) {
        const r = await AjaxLoader.api('doctors/doctor-list', { silent: true });
        _teamDoctors = r.data || [];
        const opts = (sel) => {
            const el = document.getElementById(sel);
            el.innerHTML = '<option value="">请选择</option>' + _teamDoctors.map(d =>
                `<option value="${d.id}">${Format.escape(d.name)}</option>`).join('');
            if (window.SelectPicker) SelectPicker.refresh(el);
        };
        opts('team-resident');
        opts('team-attending');
        opts('team-director');
    }
    const a = await AjaxLoader.api('patients/assignment?patient_id=' + p.id, { silent: true });
    const t = a.data || {};
    document.getElementById('team-resident').value = t.attending_doctor_id || '';
    document.getElementById('team-attending').value = t.senior_doctor_id || '';
    document.getElementById('team-director').value = t.director_doctor_id || '';
    document.getElementById('team-group').value = t.treatment_group || '';
    ['team-resident', 'team-attending', 'team-director'].forEach(id => {
        const el = document.getElementById(id);
        if (window.SelectPicker) SelectPicker.refresh(el);
    });
}

async function clinic_team_save() {
    const p = PatientContext.current();
    if (!p) { Dom.toast('请先选择患者', 'info'); return; }
    const resident = document.getElementById('team-resident').value;
    if (!resident) { Dom.toast('请选择住院医师（主管）', 'error'); return; }
    const r = await AjaxLoader.api('patients/assign', { method: 'POST', data: {
        patient_id: p.id,
        attending_doctor_id: resident,
        senior_doctor_id: document.getElementById('team-attending').value,
        director_doctor_id: document.getElementById('team-director').value,
        treatment_group: document.getElementById('team-group').value.trim()
    }});
    if (r.status === 'success') Dom.toast('三级医师已保存', 'success');
}
</script>
