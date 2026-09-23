// public/js/core/patient_context.js
// 全局患者上下文状态管理器 (Patient Context State)
// 在 SessionStorage 中锁定 current_patient_id, 医嘱/病历/病案首页间切换全程继承
const PatientContext = {
    key: 'his_patient_context',

    /**
     * 获取当前患者上下文 (对象或 null)
     */
    current() {
        try {
            return JSON.parse(sessionStorage.getItem(this.key));
        } catch (e) {
            return null;
        }
    },

    /**
     * 是否已选定患者
     */
    isActive() {
        return !!this.current();
    },

    /**
     * 锁定患者上下文
     * @param {Object} patient 患者对象 (来自 API)
     */
    set(patient) {
        if (!patient) return;
        sessionStorage.setItem(this.key, JSON.stringify(patient));
        this._afterChange();
    },

    /**
     * 清除/重置患者上下文
     */
    clear() {
        sessionStorage.removeItem(this.key);
        this._afterChange();
    },

    /**
     * 变更后的界面联动: 更新顶部摘要 + 侧边栏显隐 + 占位图显隐
     */
    _afterChange() {
        if (window.PatientSwitcher && typeof PatientSwitcher.updateTrigger === 'function') {
            PatientSwitcher.updateTrigger();
        }
        if (typeof window.refreshWorkstationLayout === 'function') {
            window.refreshWorkstationLayout();
        }
    }
};

/**
 * 全局: 根据患者上下文刷新工作站布局
 * 未选患者: 隐藏侧边栏/信息栏, 显示占位图; 已选患者: 反之, 并按需加载侧边栏
 */
window.refreshWorkstationLayout = async function() {
    const isWorkstation = window.WorkstationMode === true;
    const sidebar = document.getElementById('sidebar');
    const placeholder = document.getElementById('patient-placeholder');
    const content = document.getElementById('app-content');
    const infoBar = document.getElementById('patient-info-bar');

    if (!sidebar || !placeholder || !content) return;

    if (!isWorkstation) {
        sidebar.style.display = 'block';
        placeholder.style.display = 'none';
        content.style.display = 'block';
        if (infoBar) infoBar.style.display = 'none';
        return;
    }

    const ctx = PatientContext.current();
    if (ctx) {
        sidebar.style.display = 'block';
        placeholder.style.display = 'none';
        content.style.display = 'block';
        if (infoBar) {
            infoBar.style.display = 'flex';
            PatientContext.fillInfoBar(ctx);
        }
        if (sidebar.children.length === 0 && Auth.user) {
            try {
                const html = await fetch('/js/shared/sidebar_' + Auth.user.role + '.php').then(r => r.text());
                sidebar.innerHTML = html;
            } catch (e) {}
        }
    } else {
        sidebar.style.display = 'none';
        placeholder.style.display = 'flex';
        content.style.display = 'none';
        if (infoBar) infoBar.style.display = 'none';
    }
};

/**
 * 填充患者信息栏 (姓名/性别/年龄/科室/病区/主诊断/总费用)
 */
PatientContext.fillInfoBar = function(p) {
    if (!p) return;
    const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
    const set = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = v;
    };
    set('pib-name', p.name || '-');
    set('pib-gender', Format.gender(p.gender));
    set('pib-age', age || '-');
    set('pib-dept', p.department_name || (p.department_id ? '科室' + p.department_id : '-'));
    set('pib-ward', p.ward_name || (p.ward_id ? '病区' + p.ward_id : '-'));
    set('pib-diagnosis', p.admission_diagnosis || '-');
    set('pib-fee', '¥ ' + (p.total_fee || '--'));
};