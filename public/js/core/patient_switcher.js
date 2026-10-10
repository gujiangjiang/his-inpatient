// public/js/core/patient_switcher.js
// 患者列表下拉: 仅展示分类/科室, 点击后进入患者卡片墙 (#patients/patient_list)
const PatientSwitcher = {
    depts: [],

    /**
     * 初始化: 绑定触发按钮 + 菜单点击 + 外部点击关闭
     */
    init() {
        const trigger = document.getElementById('patient-switcher-trigger');
        const panel = document.getElementById('patient-switcher-panel');
        if (!trigger || !panel) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        });

        panel.querySelectorAll('.switcher-menu-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleAction(item.dataset.action);
            });
        });

        document.addEventListener('click', (e) => {
            const switcher = document.getElementById('patient-switcher');
            if (switcher && !switcher.contains(e.target)) panel.style.display = 'none';
        });
    },

    closePanel() {
        const panel = document.getElementById('patient-switcher-panel');
        if (panel) panel.style.display = 'none';
    },

    /**
     * 菜单动作: 添加患者(弹窗) / 主管患者 / 科室患者+ / 大小卡切换 / 出院患者
     * 主管/科室/出院 → 写入筛选并跳转卡片墙
     */
    handleAction(action) {
        const sub = document.getElementById('switcher-dept-sub');
        if (action === 'incoming') {
            this.closePanel();
            this.showIncomingModal();
        } else if (action === 'mine') {
            this.closePanel();
            this.goWall({ type: 'mine' });
        } else if (action === 'discharged') {
            this.closePanel();
            this.goWall({ type: 'discharged' });
        } else if (action === 'dept') {
            // 在「科室患者+」菜单项下方就地展开科室列表
            const show = sub.style.display !== 'block';
            sub.style.display = show ? 'block' : 'none';
            const caret = document.getElementById('switcher-dept-caret');
            if (caret) caret.classList.toggle('open', show);
            if (show) this.renderDeptList(sub);
        } else if (action === 'toggle-size') {
            const compact = sessionStorage.getItem('his_wall_compact') === '1';
            sessionStorage.setItem('his_wall_compact', compact ? '0' : '1');
            if (typeof window.patients_wall_apply_compact === 'function') window.patients_wall_apply_compact();
            Dom.toast(compact ? '已切换为大卡' : '已切换为小卡', 'info');
        }
    },

    /**
     * 跳转患者卡片墙 (筛选: active 默认 | mine 主管 | dept 科室 | discharged 出院)
     */
    goWall(filter) {
        sessionStorage.setItem('his_wall_filter', JSON.stringify(filter));
        window.navigateTo('patients', 'patient_list');
    },

    /**
     * 科室患者+: 仅渲染授权科室列表 (就地展开, 不显示患者)
     */
    async renderDeptList(sub) {
        sub.innerHTML = '<div class="switcher-loading">加载中...</div>';
        try {
            const r = await AjaxLoader.api('doctors/departments', { silent: true });
            this.depts = r.data.departments || [];
            if (this.depts.length === 0) {
                sub.innerHTML = '<div class="switcher-empty">暂无授权科室</div>';
                return;
            }
            sub.innerHTML = this.depts.map(d =>
                `<div class="switcher-menu-item switcher-dept-item" onclick="PatientSwitcher.goDept(${d.id})">
                    ${Icons.caretRight(10)}${Format.escape(d.name)}
                </div>`
            ).join('');
        } catch (e) {
            sub.innerHTML = '<div class="switcher-empty">加载失败</div>';
        }
    },

    goDept(deptId) {
        const dept = this.depts.find(d => d.id == deptId);
        this.closePanel();
        this.goWall({ type: 'dept', dept_id: deptId, department_name: dept ? dept.name : '' });
    },

    /**
     * 添加患者: 悬浮待入科列表弹窗
     */
    async showIncomingModal() {
        const overlay = Dom.modal('待入科/接收患者', '<div class="switcher-loading">加载中...</div>', [
            { text: '关闭', type: 'default' }
        ]);
        try {
            const r = await AjaxLoader.api('patients/incoming', { silent: true });
            const patients = r.data.data || [];
            const body = overlay.querySelector('.modal-body');
            if (patients.length === 0) {
                body.innerHTML = '<div class="switcher-empty">暂无待入科患者</div>';
                return;
            }
            let cards = '';
            patients.forEach(p => { cards += this.renderCardHtml(p, 'incoming'); });
            body.innerHTML = cards;
        } catch (e) {
            const body = overlay.querySelector('.modal-body');
            body.innerHTML = '<div class="switcher-empty">加载失败</div>';
        }
    },

    /**
     * 生成患者文字方块 HTML
     */
    renderCardHtml(p, tab) {
        const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
        const gender = Format.gender(p.gender);
        const attending = p.attending_name || p.doctor_name || '';
        const level = String(p.nursing_level || '3');
        const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[level] || 'Ⅲ';
        return `
            <div class="switcher-patient-block" onclick="PatientSwitcher.onSelectCard(${p.id}, '${tab}')">
                <div class="spb-line1">
                    <span class="pw-bed level-${level}" style="min-width:38px;height:20px;font-size:11px">${roman}|${Format.escape(p.bed_no || '-')}</span>
                    <span class="spb-name">${Format.escape(p.name)}</span>
                    <span class="spb-gender">${gender}/${age}</span>
                    <span class="spb-bed">${Format.escape(p.ward_name || '')}</span>
                </div>
                <div class="spb-line2">住院号 ${Format.escape(p.admission_no || '')}　主治 ${Format.escape(attending || '未指派')}　${Format.escape(p.fee_type || '')}</div>
                <div class="spb-line3">诊断 ${Format.escape(p.admission_diagnosis || '-')}</div>
            </div>
        `;
    },

    /**
     * 患者卡片点击: 待入科 → 指派医师; 其他 → 进入医嘱工作站
     */
    async onSelectCard(patientId, tab) {
        this.closePanel();
        const overlay = document.querySelector('.modal-overlay');
        if (overlay) document.body.removeChild(overlay);

        if (tab === 'incoming') {
            await this.showAssignDialog(patientId);
            return;
        }
        const patient = await this.fetchPatient(patientId);
        if (patient) {
            PatientContext.set(patient);
            this.enterPatientWorkstation();
        }
    },

    /**
     * 拉取患者完整信息
     */
    async fetchPatient(patientId) {
        try {
            const r = await AjaxLoader.api('patients/get?id=' + patientId, { silent: true });
            return r.data;
        } catch (e) {
            return null;
        }
    },

    /**
     * 指派医师确认窗口 (待入科)
     */
    async showAssignDialog(patientId) {
        const patient = await this.fetchPatient(patientId);
        if (!patient) { Dom.toast('患者信息加载失败', 'error'); return; }

        const deptDoctors = await AjaxLoader.api('doctors/doctor-list?department_id=' + (patient.department_id || ''), { silent: true });
        const doctors = deptDoctors.data || [];

        const attendingOpts = doctors.map(d => `<option value="${d.id}">${Format.escape(d.name)}</option>`).join('');
        const seniorOpts = '<option value="">不指定</option>' + doctors.map(d => `<option value="${d.id}">${Format.escape(d.name)}</option>`).join('');

        const content = `
            <div class="assign-dialog">
                <div class="form-group"><label class="form-label">患者</label>
                    <input type="text" class="form-control" value="${Format.escape(patient.name)} (${Format.escape(patient.admission_no || '')})" readonly>
                </div>
                <div class="form-group"><label class="form-label">上级医师 (主治)</label>
                    <select id="assign-attending" class="form-control">${attendingOpts}</select>
                </div>
                <div class="form-group"><label class="form-label">主任 (副主任) 医师</label>
                    <select id="assign-senior" class="form-control">${seniorOpts}</select>
                </div>
            </div>
        `;
        Dom.modal('指派医师', content, [
            { text: '确认入科', type: 'primary', onclick: async function() {
                const attending = document.getElementById('assign-attending').value;
                const senior = document.getElementById('assign-senior').value;
                if (!attending) { Dom.toast('请选择主管医师', 'error'); return; }
                await AjaxLoader.api('patients/assign', {
                    method: 'POST',
                    data: { patient_id: patientId, attending_doctor_id: attending, senior_doctor_id: senior || null }
                });
                patient.attending_name = attending;
                PatientContext.set(patient);
                PatientSwitcher.enterPatientWorkstation();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    /**
     * 进入患者工作站: 导航到医嘱工作站
     */
    enterPatientWorkstation() {
        window.navigateTo('orders', 'order_station');
    },

    /**
     * 更新顶部触发按钮的摘要信息
     */
    updateTrigger() {
        const summary = document.getElementById('current-patient-summary');
        if (!summary) return;
        const p = PatientContext.current();
        if (p) {
            const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
            summary.textContent = '患者: ' + p.name + ' (' + Format.gender(p.gender) + '/' + age + ') 床 ' + (p.bed_no || '-');
        } else {
            summary.textContent = '请选择患者';
        }
    }
};

// 挂载全局
window.PatientSwitcher = PatientSwitcher;
