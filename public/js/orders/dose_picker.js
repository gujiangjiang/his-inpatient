// public/js/orders/dose_picker.js - 剂量选择悬浮窗 (输入框 + 预设倍数快捷选项)
// 倍数基于基准剂量 (base_dosage, 开嘱时药品默认剂量) 计算; 自动计费换算为后续完善
const DosePicker = {
    panel: null,
    orderId: null,
    rootId: null,
    order: null,
    PRESETS: [0.25, 0.5, 0.75, 1, 1.5, 2, 3],
    outsideHandler: null,
    escHandler: null,

    open(orderId, rootId, event) {
        if (event) event.stopPropagation();
        this.close();
        const found = this.find(orderId, rootId);
        if (!found || found.order.status !== 'draft') return;
        this.orderId = orderId;
        this.rootId = rootId;
        this.order = found.order;

        const o = found.order;
        const panel = document.createElement('div');
        panel.className = 'os-dose-panel';
        panel.innerHTML = `
            <div class="osd-row"><label>剂量</label>
                <input type="text" id="dose-input" class="osd-input" value="${Format.escape(o.dosage || '')}" placeholder="如 1g">
                <span class="dose-unit">${Format.escape(o.dosage_unit || '')}</span>
            </div>
            <div class="dose-presets">
                ${this.PRESETS.map(p => `<span class="dose-chip" data-mult="${p}">×${p}</span>`).join('')}
            </div>
            <div class="dose-calc" id="dose-calc"></div>
            <div class="osd-actions">
                <button type="button" class="btn btn-small btn-primary" id="dose-ok">确定</button>
                <button type="button" class="btn btn-small" id="dose-cancel">取消</button>
                <span class="dose-hint">倍数基于基准剂量</span>
            </div>`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.position(event);

        panel.querySelectorAll('.dose-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const base = this.extract(o.base_dosage || o.dosage || '');
                if (!base.num) { Dom.toast('无基准剂量, 请手动输入', 'info'); return; }
                const mult = parseFloat(chip.dataset.mult);
                panel.querySelector('#dose-input').value = this.trim(base.num * mult) + base.suffix;
                this.updateCalc();
            });
        });
        panel.querySelector('#dose-input').addEventListener('input', () => this.updateCalc());
        panel.querySelector('#dose-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); this.save(); }
            if (e.key === 'Escape') this.close();
        });
        panel.querySelector('#dose-ok').addEventListener('click', () => this.save());
        panel.querySelector('#dose-cancel').addEventListener('click', () => this.close());
        this.updateCalc();

        setTimeout(() => {
            this.outsideHandler = (e) => { if (this.panel && !this.panel.contains(e.target)) this.close(); };
            this.escHandler = (e) => { if (e.key === 'Escape') this.close(); };
            document.addEventListener('mousedown', this.outsideHandler);
            document.addEventListener('keydown', this.escHandler);
        }, 0);
    },

    find(orderId, rootId) {
        const roots = (typeof OrderStation !== 'undefined') ? OrderStation.groups : [];
        const root = roots.find(g => g.id == rootId);
        if (!root) return null;
        const order = (root.id == orderId) ? root : (root.sub_orders || []).find(s => s.id == orderId);
        return order ? { root: root, order: order } : null;
    },

    /** 解析 "2g" -> {num: 2, suffix: 'g'} */
    extract(text) {
        const m = String(text || '').match(/^(\d+(?:\.\d+)?)\s*(\D*)$/);
        return m ? { num: parseFloat(m[1]), suffix: (m[2] || '').trim() } : { num: 0, suffix: '' };
    },

    trim(n) {
        return String(Math.round(n * 1000) / 1000);
    },

    updateCalc() {
        const panel = this.panel;
        if (!panel) return;
        const calc = panel.querySelector('#dose-calc');
        const baseText = this.order.base_dosage || this.order.dosage || '';
        const base = this.extract(baseText);
        const val = this.extract(panel.querySelector('#dose-input').value);
        if (!base.num || !val.num) {
            calc.textContent = baseText ? ('基准剂量: ' + baseText) : '未设置基准剂量';
            return;
        }
        const qty = Math.ceil((val.num - 1e-9) / base.num);
        const unit = this.order.dosage_unit || '件';
        calc.textContent = `基准剂量 ${baseText} · 换算倍数 ${this.trim(val.num / base.num)} · 预计计费约 ${qty} ${unit} (自动计费后续完善)`;
    },

    async save() {
        const panel = this.panel;
        if (!panel) return;
        const val = panel.querySelector('#dose-input').value.trim();
        try {
            await AjaxLoader.api('orders/update', { method: 'POST', data: { id: this.orderId, dosage: val } });
            Dom.toast('剂量已实时保存', 'success');
            this.close();
            OrderStation.refresh();
        } catch (e) { /* 已提示 */ }
    },

    position(event) {
        const p = this.panel;
        const w = 380, h = 220;
        let x, y;
        if (event && event.clientX) {
            x = event.clientX + 12;
            y = event.clientY + 8;
        } else {
            x = window.innerWidth / 2 - w / 2;
            y = 140;
        }
        p.style.left = Math.max(8, Math.min(x, window.innerWidth - w - 8)) + 'px';
        p.style.top = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
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

window.DosePicker = DosePicker;
