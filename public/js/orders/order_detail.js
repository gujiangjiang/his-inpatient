// public/js/orders/order_detail.js - 医嘱悬浮详情框 (点击医嘱行弹出, 替代行下展开)
const OrderDetail = {
    panel: null,
    orderId: null,
    rootId: null,
    outsideHandler: null,
    escHandler: null,

    open(orderId, rootId, event) {
        if (event) event.stopPropagation();
        this.close();
        const found = this.find(orderId, rootId);
        if (!found) return;
        this.orderId = orderId;
        this.rootId = rootId;
        this.order = found.order;

        const o = found.order;
        const flags = (o.billing_flags || '').split(',');
        const flagBox = (v) => `<label class="osd-flag"><input type="checkbox" class="osd-flagbox" value="${v}" ${flags.includes(v) ? 'checked' : ''}>${v}</label>`;
        const dur = this.parseDuration(o.duration);
        const isTemp = o.order_period === 'temp';
        const isMed = o.category === 'medication';
        // 计价: 正常计价 / 不计价 / 不摆药 (不摆药仅药品, 护士摆药时跳过, 可直接执行)
        const pricing = flags.includes('不计价') ? '不计价' : (flags.includes('不摆药') ? '不摆药' : '正常计价');
        const pricingOpts = ['正常计价', '不计价'].concat(isMed ? ['不摆药'] : []);

        const panel = document.createElement('div');
        panel.className = 'os-detail';
        panel.innerHTML = `
            <div class="osd-row"><label>开始时间</label><input type="text" id="osd-start" class="osd-input" data-dtpicker="datetime" value="${Format.escape((o.start_date || '').slice(0, 16))}" placeholder="YYYY-MM-DD HH:mm"></div>
            <div class="osd-row"><label>医嘱内容</label><input type="text" class="osd-input osd-readonly" value="${Format.escape(o.content)}" readonly title="医嘱内容不可直接修改"></div>
            <div class="osd-row"><label>医嘱说明</label><input type="text" id="osd-notes" class="osd-input" value="${Format.escape(o.notes || '')}" placeholder="医嘱说明"></div>
            <div class="osd-row"><label>持续时间</label>
                <input type="number" min="0" id="osd-duration" class="osd-input osd-duration-num" value="${dur.num}" placeholder="0">
                <select id="osd-duration-unit" class="osd-input osd-duration-unit">
                    ${['天', '次', '小时', '分钟'].map(u => `<option value="${u}" ${u === dur.unit ? 'selected' : ''}>${u}</option>`).join('')}
                </select>
                <label class="osd-inline-label">执行时间</label>
                <input type="text" id="osd-freqdetail" class="osd-input" value="${isTemp ? '' : Format.escape(o.freq_detail || '')}" placeholder="${isTemp ? '临时医嘱无执行时间' : '如 07-16'}" ${isTemp ? 'disabled' : ''}>
            </div>
            <div class="osd-row"><label>计价</label>
                <select id="osd-pricing" class="osd-input osd-pricing">
                    ${pricingOpts.map(p => `<option value="${p}" ${p === pricing ? 'selected' : ''}>${p}</option>`).join('')}
                </select>
                <span class="osd-flags">${flagBox('径外')}${flagBox('自费')}${flagBox('预防')}</span>
            </div>
            <div class="osd-actions">
                ${isMed ? '<button type="button" class="btn btn-small btn-primary" id="osd-manual">药品说明书</button>' : ''}
                <button type="button" class="btn btn-small btn-primary" id="osd-priceitem">计价项目</button>
                <button type="button" class="btn btn-small btn-primary" id="osd-save">保存</button>
                <span class="osd-more" id="osd-more">更多</span>
            </div>`;
        document.body.appendChild(panel);
        this.panel = panel;
        this.position(event);
        if (typeof DateTimePicker !== 'undefined') DateTimePicker.initInputs(panel);

        const manualBtn = panel.querySelector('#osd-manual');
        if (manualBtn) manualBtn.addEventListener('click', () => Dom.toast('药品说明书功能对接中', 'info'));
        panel.querySelector('#osd-priceitem').addEventListener('click', () => Dom.toast('计价项目功能对接中', 'info'));
        panel.querySelector('#osd-save').addEventListener('click', () => this.save());
        panel.querySelector('#osd-more').addEventListener('click', (e) => {
            e.stopPropagation();
            // 悬浮框保持显示, 菜单在其「更多」位置弹出, 与悬浮框共存亡
            OrderActions.show(this.rootId, e);
        });

        // 高亮该组医嘱行
        document.querySelectorAll('#os-tbody tr[data-root="' + rootId + '"]').forEach(tr => tr.classList.add('os-row-selected'));

        setTimeout(() => {
            this.outsideHandler = (e) => {
                if (!this.panel) return;
                if (this.panel.contains(e.target)) return;
                // 「更多」菜单属于悬浮框的一部分, 点击菜单不关闭悬浮框
                if (typeof OrderActions !== 'undefined' && OrderActions.popup && OrderActions.popup.contains(e.target)) return;
                this.close();
            };
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

    parseDuration(text) {
        const m = String(text || '').match(/^(\d+)\s*(\S*)$/);
        if (!m) return { num: '', unit: '天' };
        return { num: m[1], unit: ['天', '次', '小时', '分钟'].includes(m[2]) ? m[2] : '天' };
    },

    position(event) {
        const p = this.panel;
        const w = 520, h = 330;
        let x, y;
        if (event && event.clientX) {
            x = event.clientX + 12;
            y = event.clientY + 10;
        } else {
            x = window.innerWidth / 2 - w / 2;
            y = 120;
        }
        p.style.left = Math.max(8, Math.min(x, window.innerWidth - w - 8)) + 'px';
        p.style.top = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
    },

    async save() {
        const p = this.panel;
        if (!p) return;
        const start = p.querySelector('#osd-start').value.trim();
        const num = p.querySelector('#osd-duration').value.trim();
        const unit = p.querySelector('#osd-duration-unit').value;
        // 计价: 正常计价 / 不计价 / 不摆药 + 附加标签
        const pricing = p.querySelector('#osd-pricing').value;
        const flagList = Array.from(p.querySelectorAll('.osd-flagbox:checked')).map(c => c.value);
        if (pricing === '不计价' || pricing === '不摆药') flagList.push(pricing);
        const data = {
            id: this.orderId,
            start_date: start ? (start.length === 16 ? start + ':00' : start) : (this.order.start_date || ''),
            notes: p.querySelector('#osd-notes').value.trim(),
            duration: num ? (num + unit) : '',
            freq_detail: p.querySelector('#osd-freqdetail').disabled ? (this.order.freq_detail || '') : p.querySelector('#osd-freqdetail').value.trim(),
            billing_flags: flagList.join(',')
        };
        try {
            await AjaxLoader.api('orders/update', { method: 'POST', data: data });
            Dom.toast('已实时保存', 'success');
            this.close();
            OrderStation.refresh();
        } catch (e) { /* 已提示 */ }
    },

    close() {
        if (this.outsideHandler) document.removeEventListener('mousedown', this.outsideHandler);
        if (this.escHandler) document.removeEventListener('keydown', this.escHandler);
        this.outsideHandler = null;
        this.escHandler = null;
        if (this.panel && this.panel.parentNode) this.panel.parentNode.removeChild(this.panel);
        this.panel = null;
        // 悬浮框消失时, 其弹出的「更多」菜单一并关闭
        if (typeof OrderActions !== 'undefined') OrderActions.closeMenu();
        document.querySelectorAll('#os-tbody tr.os-row-selected').forEach(tr => tr.classList.remove('os-row-selected'));
    }
};

window.OrderDetail = OrderDetail;
