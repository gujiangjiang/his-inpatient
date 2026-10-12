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
     * 清除/重置患者上下文 (工作站模式下回到患者卡片墙)
     */
    clear() {
        sessionStorage.removeItem(this.key);
        if (window.WorkstationMode === true) {
            if (window.location.hash !== '#patients/patient_list') {
                window.location.hash = 'patients/patient_list';
            } else {
                this._afterChange();
            }
        } else {
            this._afterChange();
        }
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
 * - 浏览页 (患者卡片墙/床位): 无需患者上下文也可显示
 * - 未选患者: 隐藏侧边栏/信息栏, 显示占位图; 已选患者: 反之, 并按需加载侧边栏
 */
window.refreshWorkstationLayout = async function() {
    const isWorkstation = window.WorkstationMode === true;
    const sidebar = document.getElementById('sidebar');
    const placeholder = document.getElementById('patient-placeholder');
    const content = document.getElementById('app-content');
    const infoBar = document.getElementById('patient-info-bar');

    if (!sidebar || !placeholder || !content) return;

    const ctx = PatientContext.current();
    const hash = window.location.hash.replace('#', '');
    const browsePage = ['patients/patient_list', 'patients/beds'].includes(hash);

    const loadSidebarIfEmpty = async () => {
        if (sidebar.children.length === 0 && Auth.user) {
            try {
                const html = await fetch('/js/shared/sidebar_' + Auth.user.role + '.php').then(r => r.text());
                sidebar.innerHTML = html;
            } catch (e) {}
        }
    };

    // 非工作站角色 (护士/管理): 侧边栏常显
    if (!isWorkstation) {
        sidebar.style.display = 'block';
        placeholder.style.display = 'none';
        content.style.display = 'block';
        if (infoBar) infoBar.style.display = 'none';
        return;
    }

    // 浏览页 (患者卡片墙/床位): 侧边栏与患者信息栏均隐藏, 数据区常显
    if (browsePage) {
        sidebar.style.display = 'none';
        placeholder.style.display = 'none';
        content.style.display = 'block';
        if (infoBar) infoBar.style.display = 'none';
        return;
    }

    // 工作站未选患者: 侧边栏隐藏, 显示占位图
    if (!ctx) {
        sidebar.style.display = 'none';
        placeholder.style.display = 'flex';
        content.style.display = 'none';
        if (infoBar) infoBar.style.display = 'none';
        return;
    }

    // 已选患者: 侧边栏 + 患者信息栏 + 内容区
    sidebar.style.display = 'block';
    placeholder.style.display = 'none';
    content.style.display = 'block';
    if (infoBar) {
        infoBar.style.display = 'flex';
        PatientContext.fillInfoBar(ctx);
    }
    await loadSidebarIfEmpty();
};

/**
 * 填充患者信息栏 (床徽/姓名/性别年龄住院号费别/入院天数/费用/诊断)
 */
PatientContext.fillInfoBar = function(p) {
    if (!p) return;
    const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
    const set = (id, v) => {
        const el = document.getElementById(id);
        if (el) el.textContent = v;
    };

    // 床徽: 护理等级着色 (特/Ⅰ/Ⅱ/Ⅲ) + 床号
    const level = String(p.nursing_level || '3');
    const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[level] || 'Ⅲ';
    const bedEl = document.getElementById('pib-bed');
    if (bedEl) {
        bedEl.className = 'pib-bed level-' + level;
        bedEl.textContent = roman + ' | ' + (p.bed_no || '-');
    }

    set('pib-name', p.name || '-');
    const ward = p.ward_name || (p.department_name || '');
    set('pib-sub', [Format.gender(p.gender), age ? age + '岁' : '', p.admission_no || '', p.fee_type || '', ward]
        .filter(Boolean).join(' / '));

    // 入院天数
    let days = '-';
    if (p.admission_date) {
        const d = new Date(String(p.admission_date).replace(/-/g, '/'));
        if (!isNaN(d.getTime())) {
            days = Math.max(1, Math.ceil((Date.now() - d.getTime()) / 86400000)) + ' 天';
        }
    }
    set('pib-days', days);
    set('pib-fee', '¥ ' + Number(p.total_fee || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    set('pib-diagnosis', p.admission_diagnosis || '-');
    PatientContext.bindFundsPop();
};

/**
 * 费用一栏悬停浮窗 (预交费用 / 已用费用 / 可透支 / 可用余额), 仅绑定一次
 */
PatientContext.bindFundsPop = function() {
    const feeEl = document.getElementById('pib-fee');
    if (!feeEl || feeEl._fundsBound) return;
    feeEl._fundsBound = true;
    feeEl.style.cursor = 'pointer';
    feeEl.addEventListener('mouseenter', () => PatientContext.showFunds(feeEl));
    feeEl.addEventListener('mouseleave', () => PatientContext.hideFunds());
};

PatientContext.showFunds = function(anchor) {
    const ctx = PatientContext.current();
    if (!ctx) return;
    PatientContext.hideFunds();
    AjaxLoader.api('patients/funds?patient_id=' + ctx.id, { silent: true }).then(r => {
        const f = r.data || {};
        const bal = Number(f.balance || 0);
        const pop = document.createElement('div');
        pop.className = 'funds-pop';
        pop.id = 'funds-pop';
        pop.innerHTML = `
            <div class="funds-pop-row"><b>预交费用</b><span>¥ ${Number(f.prepay || 0).toFixed(2)}</span></div>
            <div class="funds-pop-row"><b>已用费用</b><span>¥ ${Number(f.used || 0).toFixed(2)}</span></div>
            <div class="funds-pop-row"><b>可透支</b><span>¥ ${Number(f.overdraft || 0).toFixed(2)}</span></div>
            <div class="funds-pop-row funds-pop-bal"><b>可用余额</b><span class="${bal < 0 ? 'neg' : ''}">¥ ${bal.toFixed(2)}</span></div>
            ${bal < 0 ? '<div class="funds-pop-note">预缴金不足，请提醒患者补交费用（检验/检查可开，药房摆药将被拦截）</div>' : ''}`;
        document.body.appendChild(pop);
        const rect = anchor.getBoundingClientRect();
        const x = Math.max(8, Math.min(rect.left, window.innerWidth - pop.offsetWidth - 8));
        pop.style.left = x + 'px';
        const belowY = rect.bottom + 6;
        pop.style.top = (belowY + pop.offsetHeight > window.innerHeight - 8)
            ? Math.max(8, rect.top - pop.offsetHeight - 6) + 'px' : belowY + 'px';
    }).catch(() => {});
};

PatientContext.hideFunds = function() {
    const pop = document.getElementById('funds-pop');
    if (pop) pop.remove();
};