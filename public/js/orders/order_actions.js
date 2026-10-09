// public/js/orders/order_actions.js - 医嘱操作 (提交/停止/作废/复制/删除/长期全停)
const OrderActions = {
    popup: null,

    find(id) {
        return OrderStation.groups.find(g => g.id == id);
    },

    show(rootId, event) {
        const root = this.find(rootId);
        if (!root) return;
        const items = [];
        if (root.status === 'draft') {
            items.push(['编辑', () => OrderEditor.open(root.category === 'medication' ? 'medication' : 'nonmedication', OrderStation.patientId, root)]);
            items.push(['提交', () => this.submit([root])]);
            items.push(['删除', () => this.removeDraft(root)]);
        } else {
            if (['pending', 'verified', 'executing'].includes(root.status)) {
                items.push(['停止', () => this.stop(root)]);
            }
            if (['verified', 'executing'].includes(root.status)) {
                items.push(['作废', () => this.invalidate(root)]);
            }
            items.push(['复制', () => this.copy(root)]);
            items.push(['护理执行记录', () => Dom.toast('护理执行记录请在护士工作站查看', 'info')]);
        }
        this.openMenu(event, items);
    },

    openMenu(event, items) {
        this.closeMenu();
        const el = document.createElement('div');
        el.className = 'os-ctx-menu';
        el.style.cssText = `position:fixed;left:${event.clientX}px;top:${event.clientY}px;z-index:2100`;
        el.innerHTML = items.map((it, i) => `<div class="os-ctx-item" data-idx="${i}">${it[0]}</div>`).join('');
        document.body.appendChild(el);
        this.popup = el;
        el.querySelectorAll('.os-ctx-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const fn = items[parseInt(item.dataset.idx, 10)][1];
                this.closeMenu();
                fn();
            });
        });
        const close = () => { this.closeMenu(); document.removeEventListener('click', close); };
        setTimeout(() => document.addEventListener('click', close), 0);
    },

    closeMenu() {
        if (this.popup && this.popup.parentNode) this.popup.parentNode.removeChild(this.popup);
        this.popup = null;
    },

    async submit(roots) {
        const ids = roots.filter(r => r.status === 'draft').map(r => r.id);
        if (!ids.length) { Dom.toast('没有可提交的草稿医嘱', 'error'); return; }
        await AjaxLoader.api('orders/submit', { method: 'POST', data: { patient_id: OrderStation.patientId, ids } });
        Dom.toast('已提交 ' + ids.length + ' 组医嘱', 'success');
        OrderStation.refresh();
    },

    submitSelected() {
        this.submit(OrderStation.selectedRoots());
    },

    removeDraft(root) {
        Dom.modal('确认删除', '<p>删除草稿医嘱「' + Format.escape(root.content) + '」' + (root.sub_orders && root.sub_orders.length ? '（含 ' + root.sub_orders.length + ' 条子医嘱）' : '') + '？</p>', [
            { text: '删除', type: 'danger', onclick: async () => {
                await AjaxLoader.api('orders/batch-delete', { method: 'POST', data: { ids: [root.id] } });
                Dom.toast('已删除', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    batchDelete() {
        const roots = OrderStation.selectedRoots();
        const drafts = roots.filter(r => r.status === 'draft');
        if (!roots.length) { Dom.toast('请先勾选医嘱', 'error'); return; }
        if (!drafts.length) { Dom.toast('只能删除草稿医嘱', 'error'); return; }
        Dom.modal('批量删除', `<p>已选 ${roots.length} 组，其中草稿 ${drafts.length} 组将被删除（非草稿自动跳过）。</p>`, [
            { text: '删除', type: 'danger', onclick: async () => {
                await AjaxLoader.api('orders/batch-delete', { method: 'POST', data: { ids: drafts.map(d => d.id) } });
                Dom.toast('已删除 ' + drafts.length + ' 组草稿', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    stop(root) {
        Dom.modal('停止医嘱', '<p>停止「' + Format.escape(root.content) + '」？</p>', [
            { text: '停止', type: 'danger', onclick: async () => {
                await AjaxLoader.api('orders/stop', { method: 'POST', data: { id: root.id } });
                Dom.toast('已停止', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    stopAllLong() {
        Dom.modal('长期全停', '<p>将停止该患者全部<b>长期</b>医嘱（待核对/已核对/执行中），确定继续？</p>', [
            { text: '确认全停', type: 'danger', onclick: async () => {
                const r = await AjaxLoader.api('orders/stop', { method: 'POST', data: { patient_id: OrderStation.patientId } });
                Dom.toast('已停止 ' + (r.data.stopped || 0) + ' 条长期医嘱', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    invalidate(root) {
        Dom.modal('作废医嘱', '<p>作废「' + Format.escape(root.content) + '」？作废后不可恢复。</p>', [
            { text: '确认作废', type: 'danger', onclick: async () => {
                await AjaxLoader.api('orders/invalidate', { method: 'POST', data: { id: root.id } });
                Dom.toast('已作废', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    async copy(root) {
        try {
            const r = await AjaxLoader.api('orders/copy', { method: 'POST', data: { id: root.id } });
            if (r.status === 'success') {
                Dom.toast('已复制为新草稿', 'success');
                OrderStation.refresh();
            }
        } catch (e) { /* 已提示 */ }
    },

    openSetPicker() {
        if (typeof OrderSets !== 'undefined') { OrderSets.open(); return; }
        Dom.toast('医嘱组套功能即将上线', 'info');
    }
};

window.OrderActions = OrderActions;
