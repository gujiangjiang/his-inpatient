<?php
header("Content-Type: text/html; charset=utf-8");
require_once __DIR__ . '/../../icons/icon.php';
?>
<div class="wall-page">
    <div class="wall-scroll">
        <div class="flex-between mb-10">
            <div style="display:flex;align-items:center;gap:10px">
                <select id="wall-dept" class="form-control" style="width:170px;padding:5px 10px" onchange="patients_wall_dept_change()">
                    <option value="">全部科室</option>
                </select>
                <span id="wall-title" class="wall-title"></span>
                <button type="button" class="wall-clear" id="wall-clear" style="display:none" onclick="patients_wall_clear_filter()">
                    <?= icon('common/actions', 'close', 10) ?>
                    返回全部
                </button>
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
        <span id="wall-footer-label">在院患者 <b id="wall-total">0</b></span>
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
var _wallFilter = { type: 'active' };

var _wallNursingText = { 'special': '特级护理', '1': '一级护理', '2': '二级护理', '3': '三级护理' };
var _wallNursingRoman = { 'special': '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' };
// 科室风险评估量表 (管理员-科室管理中配置; 护理端评估流程后续实现)
var _wallRiskLabels = { caprini: 'Caprini', padua: 'Padua', cha2ds2: 'CHA₂DS₂', hasbled: 'HAS-BLED', none: 'VTE' };

async function patients_patient_list_init() {
    document.getElementById('main-app').style.display = 'flex';
    document.getElementById('login-container').style.display = 'none';
    var user = Auth.user;
    var sidebar = await fetch('/js/shared/sidebar_' + user.role + '.php').then(function(r) { return r.text(); });
    document.getElementById('sidebar').innerHTML = sidebar;

    // 科室下拉仅展示当前医生权限范围内科室 (admin 全部)
    if (user.role === 'doctor' || user.role === 'admin') {
        var deptsRes = await AjaxLoader.api('doctors/departments', { silent: true });
        _wallDeptList = deptsRes.data.departments || [];
    } else {
        var deptsRes2 = await AjaxLoader.api('admin/department-list', { silent: true });
        _wallDeptList = deptsRes2.data || [];
    }
    var wardsRes = await AjaxLoader.api('admin/ward-list', { silent: true });
    _wallWardList = wardsRes.data || [];
    var sel = document.getElementById('wall-dept');
    _wallDeptList.forEach(function(d) {
        var opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.name;
        sel.appendChild(opt);
    });

    // 读取来自患者列表下拉的筛选 (主管/科室/出院)
    try {
        var saved = JSON.parse(sessionStorage.getItem('his_wall_filter') || 'null');
        if (saved && saved.type) _wallFilter = saved;
    } catch (e) { /* ignore */ }
    sessionStorage.removeItem('his_wall_filter');
    if (_wallFilter.type === 'dept' && _wallFilter.dept_id) sel.value = _wallFilter.dept_id;

    patients_wall_apply_compact();
    patients_wall_update_title();
    await patients_wall_load();
}

function patients_wall_dept_change() {
    var sel = document.getElementById('wall-dept');
    var deptId = sel.value;
    _wallFilter = deptId
        ? { type: 'dept', dept_id: deptId, department_name: sel.options[sel.selectedIndex].textContent }
        : { type: 'active' };
    patients_wall_update_title();
    patients_wall_load();
}

function patients_wall_clear_filter() {
    _wallFilter = { type: 'active' };
    document.getElementById('wall-dept').value = '';
    patients_wall_update_title();
    patients_wall_load();
}

function patients_wall_update_title() {
    var title = document.getElementById('wall-title');
    var footer = document.getElementById('wall-footer-label');
    var clear = document.getElementById('wall-clear');
    var t = '在院患者', f = '在院患者';
    if (_wallFilter.type === 'mine') { t = '主管患者'; f = '主管患者'; }
    else if (_wallFilter.type === 'discharged') { t = '出院患者'; f = '出院患者'; }
    else if (_wallFilter.type === 'dept') { t = (_wallFilter.department_name || '科室') + ' 在院患者'; f = t; }
    title.textContent = t;
    footer.innerHTML = f + ' <b id="wall-total">0</b>';
    clear.style.display = _wallFilter.type === 'active' ? 'none' : 'inline-block';
}

function patients_wall_apply_compact() {
    var on = sessionStorage.getItem('his_wall_compact') === '1';
    var cb = document.getElementById('wall-compact');
    if (cb) cb.checked = on;
    var grid = document.getElementById('wall-grid');
    if (grid) grid.classList.toggle('compact', on);
}
window.patients_wall_apply_compact = patients_wall_apply_compact;

async function patients_wall_load() {
    try {
        if (_wallFilter.type === 'mine') {
            var mineRes = await AjaxLoader.api('patients/my-patients', { silent: true });
            _wallPatients = mineRes.data || [];
        } else {
            var qs = new URLSearchParams();
            qs.set('status', _wallFilter.type === 'discharged' ? 'discharged' : 'active');
            if (_wallFilter.dept_id) qs.set('department_id', _wallFilter.dept_id);
            var r = await AjaxLoader.api('patients/list?' + qs.toString(), { silent: true });
            _wallPatients = r.data || [];
        }
        patients_wall_render();
    } catch (e) {
        Dom.toast('患者列表加载失败', 'error');
    }
}

function patients_wall_toggle() {
    sessionStorage.setItem('his_wall_compact', document.getElementById('wall-compact').checked ? '1' : '0');
    patients_wall_apply_compact();
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
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#909399;padding:60px 0">暂无患者</div>';
        return;
    }
    grid.innerHTML = _wallPatients.map(function(p) {
        var level = String(p.nursing_level || '3');
        var nursing = _wallNursingText[level] || '三级护理';
        var roman = _wallNursingRoman[level] || 'Ⅲ';
        var bed = roman + ' | ' + (p.bed_no || '-');
        var days = patients_wall_days(p);
        var age = patients_wall_age(p);
        var ward = [p.department_name, p.ward_name].filter(Boolean).join('-');
        var riskLabel = _wallRiskLabels[p.risk_assessment] || 'Caprini';
        var vte = patients_wall_vte(riskLabel, p.vte_caprini) + ' ' + patients_wall_vte('出血', p.vte_bleeding);
        var cls = 'pw-card' + (p.is_critical == 1 ? ' critical' : '') + (p.allergy_history ? ' allergy' : '') + (p.status === 'discharged' ? ' discharged' : '');
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
                '<span class="pw-days">费用 ¥' + Number(p.used_fee || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>' +
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
function patients_new() { showAdmissionForm(); }

/* 入院登记 (第一步: 身份与诊断; 提交后进入收住院弹窗选科/病区/床位) */
function showAdmissionForm() {
    var content = '<form id="admission-form"><div class="form-grid">' +
        Dom.formField({ name: 'name', label: '姓名', type: 'text' }, '') +
        Dom.formField({ name: 'gender', label: '性别', type: 'select', options: [{ label: '男', value: 'male' }, { label: '女', value: 'female' }] }, '') +
        Dom.formField({ name: 'birth_date', label: '出生日期', type: 'date' }, '') +
        Dom.formField({ name: 'id_card', label: '身份证号', type: 'text' }, '') +
        '<div class="form-group colspan-2"><label class="form-label">初步诊断</label>' +
        '<input type="text" name="admission_diagnosis" id="admission_diagnosis" class="form-control" placeholder="搜索 ICD-10 诊断（可留空后续补充）"></div>' +
        '<input type="hidden" name="admission_icd_code" id="admission_icd_code" value="">' +
        '</div></form>';
    Dom.modal('入院登记', content, [
        { text: '登记', type: 'primary', onclick: function() {
            var data = {};
            new FormData(document.getElementById('admission-form')).forEach(function(v, k) { data[k] = v; });
            if (!data.name) { Dom.toast('请输入姓名', 'error'); return; }
            if (!data.gender) { Dom.toast('请选择性别', 'error'); return; }
            if (!data.birth_date) { Dom.toast('请选择出生日期', 'error'); return; }
            AdmitPanel.open(data);
        } },
        { text: '取消', type: 'default' }
    ]);
    setTimeout(function() { IcdPicker.bind(document.getElementById('admission_diagnosis'), { codeEl: document.getElementById('admission_icd_code') }); }, 100);
}

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

    var feeOpts = ['', '职工医保', '居民医保', '自费', '新农合']
        .map(function(f) { return '<option value="' + f + '"' + (p.fee_type === f ? ' selected' : '') + '>' + (f || '请选择') + '</option>'; }).join('');

    var content = '<form id="patient-form">' +
        '<div class="form-grid">' +
        Dom.formField({ name: 'name', label: '姓名', type: 'text' }, p.name || '') +
        Dom.formField({ name: 'gender', label: '性别', type: 'select', options: [{ label: '男', value: 'male' }, { label: '女', value: 'female' }] }, p.gender || '') +
        Dom.formField({ name: 'birth_date', label: '生日', type: 'date' }, p.birth_date || '') +
        Dom.formField({ name: 'id_card', label: '身份证号', type: 'text' }, p.id_card || '') +
        '<div class="form-group colspan-2"><label class="form-label">入院诊断</label>' +
            '<input type="text" name="admission_diagnosis" id="admission_diagnosis" class="form-control" placeholder="搜索 ICD-10 诊断" value="' + Format.escape(p.admission_diagnosis || '') + '"></div>' +
        '<input type="hidden" name="admission_icd_code" id="admission_icd_code" value="' + Format.escape(p.admission_icd_code || '') + '">' +
        '<div class="form-group"><label class="form-label">科室</label><select name="department_id" class="form-control">' + deptHtml + '</select></div>' +
        '<div class="form-group"><label class="form-label">病区</label><select name="ward_id" class="form-control">' + wardHtml + '</select></div>' +
        '<div class="form-group"><label class="form-label">费别</label><select name="fee_type" class="form-control">' + feeOpts + '</select></div>' +
        Dom.formField({ name: 'allergy_history', label: '过敏史', type: 'text' }, p.allergy_history || '') +
        Dom.formField({ name: 'bed_no', label: '床号', type: 'text' }, p.bed_no || '') +
        '<input type="hidden" name="id" value="' + (p.id || '') + '">' +
        '</div></form>';

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
    setTimeout(function() { IcdPicker.bind(document.getElementById('admission_diagnosis'), { codeEl: document.getElementById('admission_icd_code') }); }, 100);
}

</script>
