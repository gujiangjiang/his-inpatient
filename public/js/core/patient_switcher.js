// public/js/core/patient_switcher.js
// 患者上下文切换浮动面板 (添加/本人/科室 三维度)
const PatientSwitcher = {
    activeTab: 'mine',
    deptPatientsCache: {},   // department_id => {active:[], discharged:[]}

    /**
     * 初始化: 绑定触发按钮 + Tab 切换 + 外部点击关闭
     */
    init() {
        const trigger = document.getElementById('patient-switcher-trigger');
        const panel = document.getElementById('patient-switcher-panel');
        if (!trigger || !panel) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = panel.style.display === 'block';
            panel.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) this.loadTab(this.activeTab);
        });

        const tabs = panel.querySelectorAll('.switcher-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.stopPropagation();
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.activeTab = tab.dataset.tab;
                this.handleTab(this.activeTab);
            });
        });

        document.addEventListener('click', (e) => {
            const switcher = document.getElementById('patient-switcher');
            if (switcher && !switcher.contains(e.target)) {
                panel.style.display = 'none';
            }
        });
    },

    /**
     * Tab 分发: 添加→悬浮列表弹窗; 本人/科室→面板内联渲染
     */
    handleTab(tab) {
        const panel = document.getElementById('patient-switcher-panel');
        if (tab === 'incoming') {
            // 添加: 关闭面板, 弹出悬浮患者列表
            panel.style.display = 'none';
            this.showIncomingModal();
            return;
        }
        this.loadTab(tab);
    },

    /**
     * 加载内联 Tab (本人/科室)
     */
    async loadTab(tab) {
        const content = document.getElementById('switcher-tab-content');
        if (!content) return;
        content.innerHTML = '<div class="switcher-loading">加载中...</div>';

        if (tab === 'mine') {
            try {
                const r = await AjaxLoader.api('patients/my-patients', { silent: true });
                this.renderCards(content, r.data || [], 'mine');
            } catch (e) {
                content.innerHTML = '<div class="switcher-empty">加载失败</div>';
            }
        } else {
            this.renderDepartmentTab(content);
        }
    },

    /**
     * 科室 Tab: 权限科室折叠列表 + 出院患者
     */
    async renderDepartmentTab(content) {
        try {
            const r = await AjaxLoader.api('doctors/departments', { silent: true });
            const departments = r.data.departments || [];
            let html = '';
            departments.forEach(d => {
                html += `
                    <div class="dept-group">
                        <div class="dept-group-head" onclick="PatientSwitcher.toggleDept(${d.id})">
                            <span class="dept-caret">▸</span>
                            <span class="dept-name">${Format.escape(d.name)}</span>
                            <span class="dept-count" id="dept-count-${d.id}"></span>
                        </div>
                        <div class="dept-group-body" id="dept-body-${d.id}" style="display:none"></div>
                    </div>
                `;
            });
            // 出院患者折叠组
            html += `
                <div class="dept-group">
                    <div class="dept-group-head" onclick="PatientSwitcher.toggleDischarged()">
                        <span class="dept-caret" id="discharged-caret">▸</span>
                        <span class="dept-name">出院患者</span>
                        <span class="dept-count" id="discharged-count"></span>
                    </div>
                    <div class="dept-group-body" id="discharged-body" style="display:none"></div>
                </div>
            `;
            content.innerHTML = html;
        } catch (e) {
            content.innerHTML = '<div class="switcher-empty">加载失败</div>';
        }
    },

    /**
     * 展开/折叠某科室的患者列表
     */
    async toggleDept(deptId) {
        const body = document.getElementById('dept-body-' + deptId);
        if (!body) return;
        if (body.style.display === 'block') {
            body.style.display = 'none';
            const caret = body.previousElementSibling.querySelector('.dept-caret');
            if (caret) caret.textContent = '▸';
            return;
        }
        // 缓存
        if (!this.deptPatientsCache[deptId]) {
            const r = await AjaxLoader.api('patients/by-department?department_id=' + deptId + '&status=active', { silent: true });
            this.deptPatientsCache[deptId] = r.data || [];
        }
        const patients = this.deptPatientsCache[deptId];
        document.getElementById('dept-count-' + deptId).textContent = patients.length;
        this.renderCards(body, patients, 'dept');
        body.style.display = 'block';
        const caret = body.previousElementSibling.querySelector('.dept-caret');
        if (caret) caret.textContent = '▾';
    },

    /**
     * 展开/折叠出院患者
     */
    async toggleDischarged() {
        const body = document.getElementById('discharged-body');
        if (!body) return;
        if (body.style.display === 'block') {
            body.style.display = 'none';
            document.getElementById('discharged-caret').textContent = '▸';
            return;
        }
        // 拉取所有权限科室的出院患者
        const depts = (await AjaxLoader.api('doctors/departments', { silent: true })).data.departments || [];
        let all = [];
        for (const d of depts) {
            const r = await AjaxLoader.api('patients/by-department?department_id=' + d.id + '&status=discharged', { silent: true });
            all = all.concat(r.data || []);
        }
        document.getElementById('discharged-count').textContent = all.length;
        this.renderCards(body, all, 'discharged');
        body.style.display = 'block';
        document.getElementById('discharged-caret').textContent = '▾';
    },

    /**
     * 添加 Tab: 悬浮列表弹窗 (待入科患者)
     */
    async showIncomingModal() {
        let html = '<div class="switcher-loading">加载中...</div>';
        const overlay = Dom.modal('待入科/接收患者', html, [
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
     * 生成患者文字方块 HTML (顶部切换器统一使用)
     */
    renderCardHtml(p, tab) {
        const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
        const gender = Format.gender(p.gender);
        const attending = p.attending_name || p.doctor_name || '';
        return `
            <div class="switcher-patient-block" onclick="PatientSwitcher.onSelectCard(${p.id}, '${tab}')">
                <div class="spb-line1">
                    <span class="spb-name">${Format.escape(p.name)}</span>
                    <span class="spb-gender">${gender}/${age}</span>
                    <span class="spb-bed">床 ${Format.escape(p.bed_no || '-')}</span>
                </div>
                <div class="spb-line2">住院号 ${Format.escape(p.admission_no || '')}　主治 ${Format.escape(attending || '未指派')}</div>
                <div class="spb-line3">诊断 ${Format.escape(p.admission_diagnosis || '-')}</div>
            </div>
        `;
    },

    /**
     * 渲染患者列表 (文字方块)
     */
    renderCards(container, patients, tab) {
        if (!patients || patients.length === 0) {
            container.innerHTML = '<div class="switcher-empty">暂无患者</div>';
            return;
        }
        let html = '';
        patients.forEach(p => { html += this.renderCardHtml(p, tab); });
        container.innerHTML = html;
    },

    /**
     * 患者卡片选中回调
     */
    async onSelectCard(patientId, tab) {
        const panel = document.getElementById('patient-switcher-panel');
        if (panel) panel.style.display = 'none';
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