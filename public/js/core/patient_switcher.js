// public/js/core/patient_switcher.js
// 患者上下文切换浮动面板 (添加/本人/科室 三维度)
const PatientSwitcher = {
    activeTab: 'incoming',

    /**
     * 初始化: 绑定触发按钮 + Tab 切换 + 外部点击关闭
     */
    init() {
        const trigger = document.getElementById('patient-switcher-trigger');
        const panel = document.getElementById('patient-switcher-panel');
        if (!trigger || !panel) return;

        // 触发开关
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = panel.style.display === 'block';
            panel.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) this.loadTab(this.activeTab);
        });

        // Tab 切换
        const tabs = panel.querySelectorAll('.switcher-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.stopPropagation();
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.activeTab = tab.dataset.tab;
                this.loadTab(this.activeTab);
            });
        });

        // 点击外部关闭
        document.addEventListener('click', (e) => {
            const switcher = document.getElementById('patient-switcher');
            if (switcher && !switcher.contains(e.target)) {
                panel.style.display = 'none';
            }
        });
    },

    /**
     * 加载指定 Tab 的患者列表
     * @param {string} tab incoming | mine | dept
     */
    async loadTab(tab) {
        const content = document.getElementById('switcher-tab-content');
        if (!content) return;
        content.innerHTML = '<div class="switcher-loading">加载中...</div>';

        try {
            let patients = [];
            if (tab === 'incoming') {
                const r = await AjaxLoader.api('patients/incoming', { silent: true });
                patients = r.data.data || [];
            } else if (tab === 'mine') {
                const r = await AjaxLoader.api('patients/my-patients', { silent: true });
                patients = r.data || [];
            } else {
                const r = await AjaxLoader.api('patients/list', { silent: true });
                patients = r.data || [];
            }
            this.renderCards(content, patients, tab);
        } catch (e) {
            content.innerHTML = '<div class="switcher-empty">加载失败</div>';
        }
    },

    /**
     * 渲染患者卡片列表
     * @param {HTMLElement} container 容器
     * @param {Array} patients 患者列表
     * @param {string} tab 来源 Tab
     */
    renderCards(container, patients, tab) {
        if (!patients || patients.length === 0) {
            container.innerHTML = '<div class="switcher-empty">暂无患者</div>';
            return;
        }
        let html = '';
        patients.forEach(p => {
            const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
            const gender = Format.gender(p.gender);
            const nursing = p.nursing_level || '二级护理';
            const attending = p.attending_name || p.doctor_name || '';
            html += `
                <div class="patient-card" data-id="${p.id}" onclick="PatientSwitcher.onSelectCard(${p.id}, '${tab}')">
                    <div class="patient-card-head">
                        <span class="card-bed">床 ${Format.escape(p.bed_no || '-')}</span>
                        <span class="card-name">${Format.escape(p.name)}</span>
                        <span class="card-gender-age">${gender} / ${age}</span>
                    </div>
                    <div class="patient-card-info">住院号: ${Format.escape(p.admission_no || '')}</div>
                    <div class="patient-card-info">诊断: ${Format.escape(p.admission_diagnosis || '')}</div>
                    <div class="patient-card-foot">
                        <span class="card-nursing">${Format.escape(nursing)}</span>
                        <span class="card-doctor">${Format.escape(attending || '未指派')}</span>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    },

    /**
     * 患者卡片选中回调
     * @param {number} patientId 患者ID
     * @param {string} tab 来源 Tab
     */
    async onSelectCard(patientId, tab) {
        const panel = document.getElementById('patient-switcher-panel');
        if (panel) panel.style.display = 'none';

        if (tab === 'incoming') {
            // 待入科: 弹出指派医师确认窗口
            await this.showAssignDialog(patientId);
            return;
        }
        // 本人/科室: 直接锁定为当前上下文
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

        // 获取医生列表 (同科室)
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
     * 进入患者工作站: 导航到默认模块 (病历)
     */
    enterPatientWorkstation() {
        window.navigateTo('emr', 'station');
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