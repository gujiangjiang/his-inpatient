// public/js/orders/order_actions.js - 医嘱操作 (提交/停止/作废/复制/删除/长期全停)
const OrderActions = {
    popup: null,

    find(id) {
        return OrderStation.groups.find(g => g.id == id);
    },

    show(rootId, event) {
        const root = this.find(rootId);
        if (!root) return;
        const isAuto = root.is_auto_generated == 1; // 申请类自动医嘱: 由医嘱站提交/删除/复制受控, 但支持作废
        const items = [];
        if (root.status === 'draft' && !isAuto) {
            items.push(['提交', () => this.submit([root])]);
            items.push(['删除', () => this.removeDraft(root)]);
        } else {
            // 停止: 仅长期医嘱 (临时医嘱无停止概念); 待核对/已核对/执行中均可申请停止, 护士确认后生效
            if (['pending', 'verified', 'executing'].includes(root.status) && root.order_period === 'long') {
                items.push(['停止', () => this.stop(root)]);
            }
            // 作废: 待核对/已核对/执行中均可申请 (检验/检查等自动医嘱同样支持), 护士确认后正式作废
            if (['pending', 'verified', 'executing'].includes(root.status)) {
                items.push(['作废', () => this.invalidate(root)]);
            }
            if (!isAuto) {
                items.push(['复制', () => this.copy(root)]);
            }
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
        // 视口内定位, 防止菜单溢出浏览器
        const rect = el.getBoundingClientRect();
        const x = Math.max(8, Math.min(event.clientX, window.innerWidth - rect.width - 8));
        const y = Math.max(8, Math.min(event.clientY, window.innerHeight - rect.height - 8));
        el.style.left = x + 'px';
        el.style.top = y + 'px';
        this.popup = el;
        el.querySelectorAll('.os-ctx-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const fn = items[parseInt(item.dataset.idx, 10)][1];
                this.closeMenu();
                // 菜单与医嘱悬浮详情框联动: 选择操作后一起关闭
                if (window.OrderDetail && OrderDetail.panel) OrderDetail.close();
                fn();
            });
        });
        // 统一在 closeMenu 中移除全局关闭监听 (菜单项 stopPropagation 不会再泄漏监听导致菜单第二次点击失效)
        this.docClose = () => this.closeMenu();
        setTimeout(() => {
            if (this.popup && this.docClose) document.addEventListener('click', this.docClose);
        }, 0);
    },

    closeMenu() {
        if (this.popup && this.popup.parentNode) this.popup.parentNode.removeChild(this.popup);
        this.popup = null;
        if (this.docClose) {
            document.removeEventListener('click', this.docClose);
            this.docClose = null;
        }
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

    /**
     * 快速操作: 批量停止 / 批量复制 / 批量删除 (工具栏下拉)
     */
    showQuickActions(event) {
        this.openMenu(event, [
            ['批量停止', () => this.batchStop()],
            ['批量复制', () => this.batchCopy()],
            ['批量删除', () => this.batchDelete()],
        ]);
    },

    async batchStop() {
        const roots = OrderStation.selectedRoots();
        if (!roots.length) { Dom.toast('请先勾选医嘱', 'error'); return; }
        const stoppable = roots.filter(r => r.order_period === 'long' && ['pending', 'verified', 'executing'].includes(r.status));
        if (!stoppable.length) { Dom.toast('所选医嘱中没有可停止的长期医嘱', 'error'); return; }
        Dom.modal('批量停止', `<p>确认对 ${stoppable.length} 组长期医嘱提交停止申请？（护士确认后生效）</p>`, [
            { text: '确认停止', type: 'danger', onclick: async () => {
                let ok = 0;
                for (const r of stoppable) {
                    try {
                        await AjaxLoader.api('orders/stop', { method: 'POST', data: { id: r.id } });
                        ok++;
                    } catch (e) { /* 继续 */ }
                }
                Dom.toast('已提交 ' + ok + ' 组停止申请，待护士确认', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    async batchCopy() {
        const roots = OrderStation.selectedRoots();
        if (!roots.length) { Dom.toast('请先勾选医嘱', 'error'); return; }
        const copyable = roots.filter(r => r.status !== 'draft');
        if (!copyable.length) { Dom.toast('草稿医嘱不可复制', 'error'); return; }
        let ok = 0;
        for (const r of copyable) {
            try {
                await AjaxLoader.api('orders/copy', { method: 'POST', data: { id: r.id } });
                ok++;
            } catch (e) { /* 继续 */ }
        }
        Dom.toast('已复制 ' + ok + ' 组为新草稿', 'success');
        OrderStation.refresh();
    },

    stop(root) {
        Dom.modal('停止医嘱', '<p>停止「' + Format.escape(root.content) + '」？停止申请将提交护士确认，确认后医嘱停止。</p>', [
            { text: '申请停止', type: 'danger', onclick: async () => {
                await AjaxLoader.api('orders/stop', { method: 'POST', data: { id: root.id } });
                Dom.toast('已提交停止申请，待护士确认', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    stopAllLong() {
        Dom.modal('长期全停', '<p>将对全部<b>长期</b>医嘱（待核对/已核对/执行中）提交停止申请，待护士确认后生效，确定继续？</p>', [
            { text: '确认全停', type: 'danger', onclick: async () => {
                const r = await AjaxLoader.api('orders/stop', { method: 'POST', data: { patient_id: OrderStation.patientId } });
                Dom.toast('已提交 ' + (r.data.stopping || 0) + ' 条长期医嘱停止申请', 'success');
                OrderStation.refresh();
            } },
            { text: '取消', type: 'default' }
        ]);
    },

    invalidate(root) {
        Dom.modal('作废医嘱', '<p>作废「' + Format.escape(root.content) + '」？作废申请将提交护士确认，确认后医嘱作废。</p>', [
            { text: '申请作废', type: 'danger', onclick: async () => {
                await AjaxLoader.api('orders/invalidate', { method: 'POST', data: { id: root.id } });
                Dom.toast('已提交作废申请，待护士确认', 'success');
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
