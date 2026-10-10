// public/js/core/patient_quick_switch.js - 切换患者悬浮窗 (当前页快速切换)
const PatientQuickSwitch = {
    panel: null,
    patients: [],
    currentId: null,
    outsideHandler: null,
    escHandler: null,

    async open(event) {
        if (this.panel) { this.close(); return; }
        this.currentId = PatientContext.current() ? PatientContext.current().id : null;

        const panel = document.createElement('div');
        panel.className = 'pqs-panel';
        panel.innerHTML = `
            <div class="pqs-head">
                <span>切换患者</span>
                <span class="pqs-hint">点击患者快速切换</span>
            </div>
            <div class="pqs-list"><div class="switcher-loading">加载中...</div></div>
            <div class="pqs-foot" onclick="PatientQuickSwitch.goWall()">查看全部患者 / 患者卡片墙</div>`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.position(event);

        setTimeout(() => {
            this.outsideHandler = (e) => {
                if (this.panel && !this.panel.contains(e.target) && !e.target.closest('#pib-switch-btn')) this.close();
            };
            this.escHandler = (e) => { if (e.key === 'Escape') this.close(); };
            document.addEventListener('mousedown', this.outsideHandler);
            document.addEventListener('keydown', this.escHandler);
        }, 0);

        try {
            const r = await AjaxLoader.api('patients/list', { silent: true });
            this.patients = r.data || [];
            this.render();
        } catch (e) {
            const list = this.panel && this.panel.querySelector('.pqs-list');
            if (list) list.innerHTML = '<div class="switcher-empty">加载失败</div>';
        }
    },

    position(event) {
        const w = 400;
        const btn = (event && event.target) ? event.target.closest('button') : document.getElementById('pib-switch-btn');
        const rect = btn ? btn.getBoundingClientRect() : { right: window.innerWidth - 20, bottom: 100 };
        let x = Math.min(rect.right - w, window.innerWidth - w - 12);
        let y = rect.bottom + 6;
        x = Math.max(8, x);
        y = Math.min(y, Math.max(8, window.innerHeight - 460));
        this.panel.style.left = x + 'px';
        this.panel.style.top = y + 'px';
    },

    render() {
        const list = this.panel && this.panel.querySelector('.pqs-list');
        if (!list) return;
        if (this.patients.length === 0) {
            list.innerHTML = '<div class="switcher-empty">暂无在院患者</div>';
            return;
        }
        list.innerHTML = this.patients.map(p => {
            const age = p.birth_date ? (new Date().getFullYear() - new Date(p.birth_date).getFullYear()) : '';
            const level = String(p.nursing_level || '3');
            const roman = { special: '特', '1': 'Ⅰ', '2': 'Ⅱ', '3': 'Ⅲ' }[level] || 'Ⅲ';
            const active = p.id == this.currentId;
            return `<div class="pqs-row${active ? ' active' : ''}" onclick="PatientQuickSwitch.select(${p.id})">
                <span class="pw-bed level-${level}" style="min-width:38px;height:20px;font-size:11px">${roman}|${Format.escape(p.bed_no || '-')}</span>
                <span class="pqs-name">${Format.escape(p.name)}</span>
                <span class="pqs-meta">${Format.gender(p.gender)}/${age || '-'}　${Format.escape(p.ward_name || '')}</span>
                ${active ? '<span class="pqs-cur">当前</span>' : ''}
            </div>`;
        }).join('');
    },

    async select(patientId) {
        if (patientId == this.currentId && document.getElementById('os-tbody')) { this.close(); return; }
        try {
            const r = await AjaxLoader.api('patients/get?id=' + patientId, { silent: true });
            if (!r.data) return;
            PatientContext.set(r.data);
            this.close();
            // 当前已在医嘱工作站则就地刷新为该患者, 否则进入医嘱工作站
            if (document.getElementById('os-tbody') && typeof OrderStation !== 'undefined') {
                OrderStation.init();
            } else {
                window.navigateTo('orders', 'order_station');
            }
        } catch (e) { /* 已提示 */ }
    },

    goWall() {
        this.close();
        window.navigateTo('patients', 'patient_list');
    },

    close() {
        if (this.outsideHandler) document.removeEventListener('mousedown', this.outsideHandler);
        if (this.escHandler) document.removeEventListener('keydown', this.escHandler);
        this.outsideHandler = null;
        this.escHandler = null;
        if (this.panel && this.panel.parentNode) this.panel.parentNode.removeChild(this.panel);
        this.panel = null;
    }
};

window.PatientQuickSwitch = PatientQuickSwitch;
