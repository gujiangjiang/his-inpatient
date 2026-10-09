<?php header("Content-Type: text/html; charset=utf-8"); ?>
<div class="wall-page">
    <div class="wall-scroll">
        <div class="flex-between mb-10">
            <div style="display:flex;align-items:center;gap:10px">
                <select id="wall-dept" class="form-control" style="width:180px;padding:5px 10px" onchange="patients_wall_load()">
                    <option value="">全部科室</option>
                </select>
                <label style="font-size:13px;color:#606266;display:flex;align-items:center;gap:4px;cursor:pointer">
                    <input type="checkbox" id="wall-compact" onchange="patients_wall_toggle()"> 小卡
                </label>
                <span id="wall-count" style="font-size:13px;color:#909399"></span>
            </div>
            <div>
                <button class="btn btn-primary btn-small" onclick="patients_new()">入院登记</button>
                <button class="btn btn-small" onclick="patients_wall_load()">刷新</button>
            </div>
        </div>
        <div class="wall-grid" id="wall-grid"></div>
    </div>
    <div class="wall-footer">
        <span>主管患者 <b id="wall-total">0</b></span>
        <span style="opacity:.75">点击卡片进入医嘱工作站</span>
        <span class="wall-legend">
            <span><i class="dot dot-special"></i>特级护理</span>
            <span><i class="dot dot-l1"></i>一级护理</span>
            <span><i class="dot dot-l2"></i>二级护理</span>
            <span><i class="dot dot-l3"></i>三级护理</span>
        </span>
    </div>
</div>

<script>
var _wallPatients = [], _wallDeptList = [], _wallWardList = [];

var _wallNursingText = { 'special': '特级护理', '1': '一级护理', '2': '二级护理', '3': '三级护理' };
var _wallNursingRoman = { 'special': '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' };

async function patients_patient_list_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    var user = Auth.user;
    var sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(function(r) { return r.text(); });
    document.getElementById('sidebar').innerHTML = sidebar;

    var deptsRes = await AjaxLoader.api('admin/department-list', { silent: true });
    _wallDeptList = deptsRes.data || [];
    var wardsRes = await AjaxLoader.api('admin/ward-list', { silent: true });
    _wallWardList = wardsRes.data || [];
    var sel = document.getElementById('wall-dept');
    _wallDeptList.forEach(function(d) {
        var opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.name;
        sel.appendChild(opt);
    });
    await patients_wall_load();
}

async function patients_wall_load() {
    var deptId = document.getElementById('wall-dept').value;
    var qs = deptId ? '?department_id=' + deptId : '';
    var r = await AjaxLoader.api('patients/list' + qs, { silent: true });
    _wallPatients = r.data || [];
    patients_wall_render();
}

function patients_wall_toggle() {
    document.getElementById('wall-grid').classList.toggle('compact', document.getElementById('wall-compact').checked);
}

function patients_wall_days(p) {
    if (!p.admission_date) return '';
    var d = new Date(String(p.admission_date).replace(/-/g, '/'));
    if (isNaN(d.getTime())) return '';
    return Math.max(1, Math.ceil((Date.now() - d.getTime()) / 86400000)) + '天';
}

function patients_wall_age(p) {
    if (!p.birth_date) return '';
    var b = new Date(p.birth_date);
    if (isNaN(b.getTime())) return '';
    return (new Date().getFullYear() - b.getFullYear()) + '岁';
}

function patients_wall_vte(label, value) {
    if (!value) return '';
    var cls = value.indexOf('高') >= 0 ? 'vte-high' : (value.indexOf('中') >= 0 ? 'vte-mid' : 'vte-low');
    return '<span class="pw-vte ' + cls + '">' + label + ' ' + Format.escape(value) + '</span>';
}

function patients_wall_render() {
    var grid = document.getElementById('wall-grid');
    document.getElementById('wall-count').textContent = _wallPatients.length + ' 人';
    document.getElementById('wall-total').textContent = _wallPatients.length;
    if (_wallPatients.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#909399;padding:60px 0">本科室暂无在院患者</div>';
        return;
    }
    grid.innerHTML = _wallPatients.map(function(p) {
        var level = String(p.nursing_level || '3');
        var nursing = _wallNursingText[level] || '三级护理';
        var roman = _wallNursingRoman[level] || 'Ⅲ';
        var bed = roman + '|' + (p.bed_no || '-');
        var days = patients_wall_days(p);
        var age = patients_wall_age(p);
        var ward = [p.department_name, p.ward_name].filter(Boolean).join('-');
        var vte = patients_wall_vte('Caprini', p.vte_caprini) + ' ' + patients_wall_vte('出血', p.vte_bleeding);
        var cls = 'pw-card' + (p.is_critical == 1 ? ' critical' : '') + (p.allergy_history ? ' allergy' : '');
        var admDate = p.admission_date ? String(p.admission_date).slice(0, 10) : '';
        return '<div class="' + cls + '" onclick="patients_wall_enter(' + p.id + ')" title="进入医嘱工作站">' +
            '<div class="pw-head">' +
                '<span class="pw-bed level-' + level + '">' + bed + '</span>' +
                '<span class="pw-name">' + Format.escape(p.name) + '</span>' +
                '<span class="pw-ward">' + Format.escape(ward) + '</span>' +
            '</div>' +
            '<div class="pw-line">' + Format.gender(p.gender) + ' / ' + (age || '-') + ' / ' + Format.escape(p.admission_no || '') + ' / ' + nursing + '</div>' +
            '<div class="pw-line"><span class="pw-label">ID:</span> ' + Format.escape(p.patient_no || p.id) + (p.attending_name ? ' <span class="pw-label">主治:</span> ' + Format.escape(p.attending_name) : '') + '</div>' +
            '<div class="pw-line"><span class="pw-label">入院:</span> ' + admDate + ' <b style="color:#f56c6c">' + days + '</b> <span class="pw-label">费别:</span> ' + Format.escape(p.fee_type || '-') + '</div>' +
            '<div class="pw-line pw-diagnosis"><span class="pw-label">诊断:</span> ' + Format.escape(p.admission_diagnosis || '-') + '</div>' +
            '<div class="pw-line"><span class="pw-label">病区:</span> ' + Format.escape(p.ward_name || '-') + '护理</div>' +
            '<div class="pw-foot">' + vte +
                '<span class="pw-days">费用 ¥' + Number(p.total_fee || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>' +
            '</div>' +
        '</div>';
    }).join('');
}

async function patients_wall_enter(id) {
    var r = await AjaxLoader.api('patients/get?id=' + id, { silent: true });
    if (!r.data) return;
    PatientContext.set(r.data);
    if (typeof PatientSwitcher !== 'undefined') PatientSwitcher.enterPatientWorkstation();
}

/* ==== 入院登记 / 编辑 / 出院 (沿用原有逻辑) ==== */
function patients_new() { showPatientForm({}); }

function patients_edit(id) {
    var p = _wallPatients.find(function(x) { return x.id == id; });
    if (p) showPatientForm(p);
}

function patients_discharge(id) {
    Dom.modal('确认出院', '<p>确认患者出院吗？</p>', [
        { text: '确认', type: 'danger', onclick: async function() {
            await AjaxLoader.api('patients/discharge', { method: 'POST', data: { id: id } });
            patients_wall_load();
        } },
        { text: '取消', type: 'default' }
    ]);
}

function showPatientForm(p) {
    var deptHtml = '<option value="">请选择</option>';
    _wallDeptList.forEach(function(d) { deptHtml += '<option value="' + d.id + '"' + (p.department_id == d.id ? ' selected' : '') + '>' + Format.escape(d.name) + '</option>'; });
    var wardHtml = '<option value="">请选择</option>';
    _wallWardList.forEach(function(w) { wardHtml += '<option value="' + w.id + '"' + (p.ward_id == w.id ? ' selected' : '') + '>' + Format.escape(w.name) + '</option>'; });

    var nursingOpts = [['special', '特级护理'], ['1', '一级护理'], ['2', '二级护理'], ['3', '三级护理']]
        .map(function(o) { return '<option value="' + o[0] + '"' + (String(p.nursing_level) === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('');
    var feeOpts = ['', '职工医保', '居民医保', '自费', '新农合']
        .map(function(f) { return '<option value="' + f + '"' + (p.fee_type === f ? ' selected' : '') + '>' + (f || '请选择') + '</option>'; }).join('');

    var content = '<form id="patient-form" style="max-height:70vh;overflow-y:auto">' +
        Dom.formField({ name: 'name', label: '姓名', type: 'text' }, p.name || '') +
        Dom.formField({ name: 'gender', label: '性别', type: 'select', options: [{ label: '男', value: 'male' }, { label: '女', value: 'female' }] }, p.gender || '') +
        Dom.formField({ name: 'birth_date', label: '生日', type: 'date' }, p.birth_date || '') +
        Dom.formField({ name: 'id_card', label: '身份证号', type: 'text' }, p.id_card || '') +
        Dom.formField({ name: 'admission_diagnosis', label: '入院诊断', type: 'text' }, p.admission_diagnosis || '') +
        '<input type="hidden" name="admission_icd_code" id="admission_icd_code" value="' + Format.escape(p.admission_icd_code || '') + '">' +
        '<div class="form-group"><label class="form-label">科室</label><select name="department_id" class="form-control">' + deptHtml + '</select></div>' +
        '<div class="form-group"><label class="form-label">病区</label><select name="ward_id" class="form-control">' + wardHtml + '</select></div>' +
        '<div class="form-group"><label class="form-label">护理等级</label><select name="nursing_level" class="form-control">' + nursingOpts + '</select></div>' +
        '<div class="form-group"><label class="form-label">费别</label><select name="fee_type" class="form-control">' + feeOpts + '</select></div>' +
        Dom.formField({ name: 'allergy_history', label: '过敏史', type: 'text' }, p.allergy_history || '') +
        Dom.formField({ name: 'bed_no', label: '床号', type: 'text' }, p.bed_no || '') +
        '<input type="hidden" name="id" value="' + (p.id || '') + '">' +
        '</form>';

    Dom.modal('患者信息', content, [
        { text: '保存', type: 'primary', onclick: async function() {
            var data = {};
            new FormData(document.getElementById('patient-form')).forEach(function(v, k) { data[k] = v; });
            var result = await AjaxLoader.api('patients/save', { method: 'POST', data: data });
            if (result.status === 'success') {
                Dom.toast('保存成功', 'success');
                patients_wall_load();
            }
        } },
        { text: '取消', type: 'default' }
    ]);
    setTimeout(function() { IcdSearch.init('admission_diagnosis', 'admission_icd_code', 'admission_diagnosis'); }, 100);
}

function navigateTo(module, page) {
    AjaxLoader.loadPage(module, page);
}
</script>
