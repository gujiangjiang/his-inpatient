// public/js/patients/admit_panel.js - 收住院弹窗 (基础信息 → 科室 → 病区 → 床位 → 确认收住)
// 说明: 后续完整移植至门诊一体化系统 (门诊转归"住院"后调用); 当前在 HIS 内测试
const AdmitPanel = {
    info: null,      // { name, gender, birth_date, id_card, admission_diagnosis, admission_icd_code }
    depts: [],
    wards: [],
    beds: [],
    deptId: '',
    pickedWard: null,
    pickedBed: null,
    overlay: null,

    async open(info) {
        this.info = info || {};
        this.deptId = '';
        this.pickedWard = null;
        this.pickedBed = null;
        this.beds = [];
        const [d, w] = await Promise.all([
            AjaxLoader.api('admin/department-list', { silent: true }),
            AjaxLoader.api('admin/ward-list', { silent: true })
        ]);
        this.depts = (d.data || []).filter(function (x) { return x.is_active == 1; });
        this.wards = (w.data || []).filter(function (x) { return x.is_active == 1; });
        this.overlay = Dom.modal('收住院', this.html(), [
            { text: '确认收住', type: 'primary', onclick: () => this.confirmStart() },
            { text: '取消', type: 'default' }
        ]);
        this.renderBeds();
    },

    html() {
        const i = this.info;
        const age = this.calcAge(i.birth_date);
        return `
        <div class="kv-grid mb-10">
            <div class="kv-item"><b>姓名</b><span>${Format.escape(i.name || '')}</span></div>
            <div class="kv-item"><b>性别</b><span>${Format.gender(i.gender)}</span></div>
            <div class="kv-item"><b>出生日期</b><span>${Format.escape(i.birth_date || '')}</span></div>
            <div class="kv-item"><b>年龄</b><span>${age ? age + ' 岁' : '-'}</span></div>
            <div class="kv-item"><b>身份证号</b><span>${Format.escape(i.id_card || '-')}</span></div>
            <div class="kv-item"><b>初步诊断</b><span>${Format.escape(i.admission_diagnosis || '-')}</span></div>
        </div>
        <div class="form-grid">
            <div class="form-group"><label class="form-label">科室</label>
                <select id="admit-dept" class="form-control" data-spicker="off" onchange="AdmitPanel.onDept(this.value)">
                    <option value="">请选择科室</option>
                    ${this.depts.map(x => `<option value="${x.id}">${Format.escape(x.name)}</option>`).join('')}
                </select></div>
            <div class="form-group"><label class="form-label">病区</label>
                <select id="admit-ward" class="form-control" data-spicker="off" onchange="AdmitPanel.onWard(this.value)">
                    <option value="">请先选择科室</option>
                </select></div>
        </div>
        <div class="form-group" style="margin-top:4px">
            <label class="form-label">床位 <span class="fs-12 text-muted" id="admit-bed-hint"></span></label>
            <div class="bed-grid" id="admit-bed-grid"></div>
        </div>`;
    },

    calcAge(birth) {
        if (!birth) return '';
        const d = new Date(String(birth).replace(/-/g, '/'));
        if (isNaN(d.getTime())) return '';
        return Math.max(0, new Date().getFullYear() - d.getFullYear());
    },

    onDept(id) {
        this.deptId = id;
        this.pickedWard = null;
        this.pickedBed = null;
        this.beds = [];
        const sel = document.getElementById('admit-ward');
        if (!id) {
            sel.innerHTML = '<option value="">请先选择科室</option>';
            this.renderBeds();
            return;
        }
        const list = this.wards.filter(function (w) {
            return !w.department_id || String(w.department_id) === String(id);
        });
        sel.innerHTML = '<option value="">请选择病区</option>' + list.map(function (w) {
            return '<option value="' + w.id + '">' + Format.escape(w.name) + '</option>';
        }).join('');
        this.renderBeds();
    },

    async onWard(wardId) {
        this.pickedWard = wardId ? Number(wardId) : null;
        this.pickedBed = null;
        if (!wardId) { this.beds = []; this.renderBeds(); return; }
        const r = await AjaxLoader.api('patients/beds?ward_id=' + wardId, { silent: true });
        this.beds = r.data || [];
        this.renderBeds();
    },

    renderBeds() {
        const grid = document.getElementById('admit-bed-grid');
        const hint = document.getElementById('admit-bed-hint');
        if (!grid) return;
        if (!this.pickedWard) {
            grid.innerHTML = '<div class="empty-state" style="width:100%">请先选择病区</div>';
            if (hint) hint.textContent = '';
            return;
        }
        if (!this.beds.length) {
            grid.innerHTML = '<div class="empty-state" style="width:100%">该病区暂无床位</div>';
            return;
        }
        const freeCnt = this.beds.filter(function (b) { return b.status !== 'occupied'; }).length;
        if (hint) hint.textContent = '空闲 ' + freeCnt + ' / 共 ' + this.beds.length;
        const self = this;
        grid.innerHTML = this.beds.map(function (b) {
            if (b.status === 'occupied') {
                // 占用床位显示占位符, 不可选择 (杜绝一床多人)
                return '<div class="bed-cell occupied admitted-lock" title="占用：' + Format.escape(b.patient_name || '') + '">' +
                    '<div class="bed-no">' + Format.escape(b.bed_no) + '</div>' +
                    '<div class="bed-patient">占用</div></div>';
            }
            const picked = Number(self.pickedBed) === Number(b.id) ? ' picked' : '';
            return '<div class="bed-cell free bed-pick' + picked + '" onclick="AdmitPanel.pickBed(' + b.id + ')">' +
                '<div class="bed-no">' + Format.escape(b.bed_no) + '</div>' +
                '<div class="bed-patient">空闲</div></div>';
        }).join('');
    },

    pickBed(id) {
        this.pickedBed = Number(this.pickedBed) === Number(id) ? null : Number(id);
        this.renderBeds();
    },

    validate() {
        if (!this.deptId) { Dom.toast('请选择科室', 'error'); return false; }
        if (!this.pickedWard) { Dom.toast('请选择病区', 'error'); return false; }
        if (!this.pickedBed) { Dom.toast('请选择床位', 'error'); return false; }
        return true;
    },

    /* 二次确认: 患者xxx, 诊断xxx, 收住xxx病区xxx科xx床 */
    confirmStart() {
        if (!this.validate()) return;
        const i = this.info;
        const dept = this.depts.find(d => String(d.id) === String(this.deptId)) || {};
        const ward = this.wards.find(w => Number(w.id) === Number(this.pickedWard)) || {};
        const bed = this.beds.find(b => Number(b.id) === Number(this.pickedBed)) || {};
        const age = this.calcAge(i.birth_date);
        const summary = '患者 ' + (i.name || '') + '（' + Format.gender(i.gender) + (age ? ' · ' + age + '岁' : '') + '）'
            + '，诊断 ' + (i.admission_diagnosis || '-')
            + '，收住 ' + (dept.name || '') + ' ' + (ward.name || '') + ' ' + (bed.bed_no || '') + ' 床。';
        Dom.modal('确认收住院', '<p style="font-size:14px;line-height:1.9">' + Format.escape(summary) + '</p>' +
            '<p class="fs-12 text-muted">确认后将完成患者登记并占用该床位。</p>', [
            { text: '确认收住', type: 'primary', onclick: () => this.submit() },
            { text: '取消', type: 'default' }
        ]);
    },

    async submit() {
        const i = this.info;
        const bed = this.beds.find(b => Number(b.id) === Number(this.pickedBed)) || {};
        const r = await AjaxLoader.api('patients/save', { method: 'POST', data: {
            name: i.name,
            gender: i.gender,
            birth_date: i.birth_date,
            id_card: i.id_card || '',
            admission_diagnosis: i.admission_diagnosis || '',
            admission_icd_code: i.admission_icd_code || '',
            department_id: this.deptId,
            ward_id: this.pickedWard,
            bed_no: bed.bed_no || ''
        }});
        if (r.status === 'success') {
            Dom.toast('患者已收住入院', 'success');
            if (this.overlay && this.overlay.parentNode) this.overlay.remove();
            if (typeof patients_wall_load === 'function') patients_wall_load();
        }
    }
};
