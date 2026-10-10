// public/js/orders/order_sets.js - 医嘱组套 (路径) 选择与套用
const OrderSets = {
    overlay: null,
    sets: [],

    async open() {
        this.overlay = Dom.modal('医嘱组套 / 路径', '<div id="osets-box"><div class="switcher-loading">加载中...</div></div>', [
            { text: '关闭', type: 'default' }
        ]);
        try {
            const r = await AjaxLoader.api('orders/set-list', { silent: true });
            this.sets = r.data || [];
            this.render();
        } catch (e) {
            document.getElementById('osets-box').innerHTML = '<div class="switcher-empty">加载失败</div>';
        }
    },

    render() {
        const box = document.getElementById('osets-box');
        if (!box) return;
        if (this.sets.length === 0) {
            box.innerHTML = '<div class="switcher-empty">暂无组套，可在开嘱编辑器中「存为组套」</div>';
            return;
        }
        box.innerHTML = this.sets.map(s => {
            const cat = s.category === 'medication' ? '药品' : '非药品';
            const items = (s.items || []).map(i =>
                `<div class="osets-item">· ${Format.escape(i.content)} ${Format.escape(i.dosage || '')}${Format.escape(i.dosage_unit || '')} ${Format.escape(i.usage_name || '')} ${Format.escape(i.freq_code || '')}</div>`
            ).join('');
            return `<div class="osets-set">
                <div class="osets-head">
                    <span class="osets-name">${Format.escape(s.name)}</span>
                    <span class="os-tag os-tag-yufang">${cat}</span>
                    <span class="osets-meta">${s.item_count} 条 · ${Format.escape(s.creator_name || '系统')}</span>
                    <button type="button" class="btn btn-small btn-primary" onclick="OrderSets.apply(${s.id})">套用</button>
                </div>
                <div class="osets-items">${items}</div>
            </div>`;
        }).join('');
    },

    async apply(setId) {
        if (!OrderStation.patientId) { Dom.toast('请先选择患者', 'error'); return; }
        try {
            const r = await AjaxLoader.api('orders/set-apply', { method: 'POST', data: { set_id: setId, patient_id: OrderStation.patientId } });
            Dom.toast('已套用组套，生成 ' + (r.data.count || 0) + ' 条草稿', 'success');
            if (this.overlay && this.overlay.parentNode) this.overlay.parentNode.removeChild(this.overlay);
            this.overlay = null;
            OrderStation.refresh();
        } catch (e) { /* 已提示 */ }
    },

};

window.OrderSets = OrderSets;
