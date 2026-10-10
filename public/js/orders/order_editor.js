// public/js/orders/order_editor.js - 开嘱编辑器 (成组药品 / 非药品, 长期临时)
const OrderEditor = {
    patientId: null,
    category: 'medication',
    editId: null,
    items: [],
    overlay: null,

    FREQS: [
        ['', '无'], ['qd', 'qd 每日1次'], ['bid', 'bid 每日2次'], ['tid', 'tid 每日3次'],
        ['qid', 'qid 每日4次'], ['q6h', 'q6h 每6小时'], ['q8h', 'q8h 每8小时'],
        ['q12h', 'q12h 每12小时'], ['q2h', 'q2h 每2小时'], ['qn', 'qn 每晚'],
        ['prn', 'prn 必要时'], ['st', 'st 立即']
    ],
    USAGES: ['静脉输液', '静脉注射', '口服', '皮下注射', '肌肉注射', '外用', '雾化吸入', '护理', '测量', '饮食', '陪护'],
    FLAGS: [['径外', '径外'], ['自费', '自费'], ['不计价', '不计价'], ['预防', '预防']],

    /**
     * 打开编辑器; editOrder 传入时为修改草稿 (根医嘱对象)
     */
    open(category, patientId, editOrder) {
        this.category = category;
        this.patientId = patientId;
        this.editId = editOrder ? editOrder.id : null;
        this.items = [];

        if (editOrder) {
            const rows = [editOrder].concat(editOrder.sub_orders || []);
            this.items = rows.map(r => ({
                content: r.content,
                drug_id: r.drug_id,
                dosage: r.dosage || '',
                dosage_unit: r.dosage_unit || '',
                freq_code: r.freq_code || r.frequency || '',
                freq_detail: r.freq_detail || '',
                billing_flags: r.billing_flags || ''
            }));
        }

        const isMed = category === 'medication';
        const usageOpts = this.USAGES.map(u => `<option value="${u}">${u}</option>`).join('');
        const periodLong = !editOrder || (editOrder.order_period !== 'temp');
        const groupUsage = editOrder ? (editOrder.usage_name || '') : '';
        const groupRate = editOrder ? (editOrder.usage_rate || '') : '';

        const html = `
        <div class="order-editor">
            <div class="oe-head">
                <label class="os-filter-radio"><input type="radio" name="oe-period" value="long" ${periodLong ? 'checked' : ''}> 长期医嘱</label>
                <label class="os-filter-radio"><input type="radio" name="oe-period" value="temp" ${!periodLong ? 'checked' : ''}> 临时医嘱</label>
                <span class="os-sep"></span>
                <label>用法</label>
                <select id="oe-usage" class="form-control" style="width:130px;padding:4px 8px">${usageOpts}</select>
                ${isMed ? '<label>滴速</label><input id="oe-rate" class="form-control" style="width:100px;padding:4px 8px" placeholder="如 60滴/分" value="' + Format.escape(groupRate) + '">' : ''}
            </div>
            <div class="oe-groupbox">
                <table class="oe-items">
                    <thead><tr>
                        <th class="oe-item-content">医嘱内容</th>
                        <th style="width:100px">剂量</th>
                        <th style="width:56px">单位</th>
                        <th style="width:88px">频次</th>
                        <th style="width:100px">执行时间</th>
                        <th style="width:170px">标签</th>
                        <th style="width:30px"></th>
                    </tr></thead>
                    <tbody id="oe-tbody"></tbody>
                </table>
                <button type="button" class="oe-add-item" id="oe-add">${isMed ? '＋ 添加药品' : '＋ 添加一条'}</button>
            </div>
            <div class="oe-foot">
                <button type="button" class="btn btn-primary btn-small" onclick="OrderEditor.save(false)">存为草稿</button>
                <button type="button" class="btn btn-success btn-small" onclick="OrderEditor.save(true)">保存并提交</button>
                ${!editOrder && typeof OrderSets !== 'undefined' ? '<button type="button" class="btn btn-small" onclick="OrderSets.saveFromEditor()">存为组套</button>' : ''}
                <button type="button" class="btn btn-small" onclick="OrderEditor.close()">取消</button>
                <span class="oe-tip">${isMed ? '成组药品将共享用法/滴速' : '非药品逐条保存为独立医嘱'}</span>
            </div>
        </div>`;

        this.overlay = Dom.modal(isMed ? '开药品医嘱' : '开非药品医嘱', html, []);
        const usageSel = document.getElementById('oe-usage');
        if (groupUsage) usageSel.value = groupUsage;
        document.getElementById('oe-add').addEventListener('click', () => {
            if (this.category === 'medication') {
                DrugSearch.open(drug => this.addDrug(drug));
            } else {
                this.items.push({ content: '', dosage: '', dosage_unit: '', freq_code: '', freq_detail: '', billing_flags: '' });
                this.renderItems();
                const inputs = document.querySelectorAll('.oe-content');
                if (inputs.length) inputs[inputs.length - 1].focus();
            }
        });
        this.renderItems();
    },

    addDrug(drug) {
        this.items.push({
            content: drug.name,
            drug_id: drug.id,
            dosage: drug.default_dosage || '',
            dosage_unit: drug.unit || '',
            freq_code: drug.default_freq || '',
            freq_detail: '',
            billing_flags: ''
        });
        const usageSel = document.getElementById('oe-usage');
        if (usageSel && !usageSel.value && drug.default_route) usageSel.value = drug.default_route;
        this.renderItems();
    },

    removeItem(idx) {
        this.items.splice(idx, 1);
        this.renderItems();
    },

    renderItems() {
        const tbody = document.getElementById('oe-tbody');
        if (!tbody) return; // 编辑器已关闭
        const isMed = this.category === 'medication';
        const freqOpts = f => this.FREQS.map(o => `<option value="${o[0]}" ${o[0] === f ? 'selected' : ''}>${o[1]}</option>`).join('');
        tbody.innerHTML = this.items.map((it, i) => {
            const flags = (it.billing_flags || '').split(',');
            const flagBoxes = this.FLAGS.map(f =>
                `<label style="font-size:11px;margin-right:5px;white-space:nowrap"><input type="checkbox" class="oe-flag" data-idx="${i}" value="${f[0]}" ${flags.includes(f[0]) ? 'checked' : ''}>${f[1]}</label>`
            ).join('');
            return `<tr>
                <td><input class="oe-content" data-idx="${i}" value="${Format.escape(it.content)}" placeholder="${isMed ? '药品名称' : '医嘱内容, 如 一级护理'}" ${isMed ? 'readonly' : ''}></td>
                <td><input class="oe-dosage" data-idx="${i}" value="${Format.escape(it.dosage)}" placeholder="如 0.1g"></td>
                <td><input class="oe-unit" data-idx="${i}" value="${Format.escape(it.dosage_unit)}" placeholder="片/支"></td>
                <td><select class="oe-freq" data-idx="${i}">${freqOpts(it.freq_code)}</select></td>
                <td><input class="oe-freq-detail" data-idx="${i}" value="${Format.escape(it.freq_detail)}" placeholder="如 07-16"></td>
                <td>${flagBoxes}</td>
                <td><button type="button" class="oe-del" onclick="OrderEditor.removeItem(${i})">${svgIcon('common/actions/close', 12)}</button></td>
            </tr>`;
        }).join('');
        // 频次变更时自动生成执行时间 (若执行时间为空)
        tbody.querySelectorAll('.oe-freq').forEach(sel => {
            sel.addEventListener('change', () => {
                const detail = tbody.querySelector('.oe-freq-detail[data-idx="' + sel.dataset.idx + '"]');
                if (!detail || detail.value) return;
                const auto = (typeof OrderStation !== 'undefined') ? OrderStation.autoFreqDetail(sel.value) : '';
                if (auto) detail.value = auto;
            });
        });
    },

    collect() {
        const period = document.querySelector('input[name="oe-period"]:checked').value;
        const usage = document.getElementById('oe-usage').value;
        const rateEl = document.getElementById('oe-rate');
        const rate = rateEl ? rateEl.value : '';
        const items = [];
        document.querySelectorAll('#oe-tbody tr').forEach((tr, i) => {
            const q = cls => tr.querySelector('.' + cls);
            const flags = Array.from(tr.querySelectorAll('.oe-flag:checked')).map(c => c.value).join(',');
            items.push({
                content: q('oe-content').value.trim(),
                drug_id: this.items[i] ? this.items[i].drug_id : null,
                dosage: q('oe-dosage').value.trim(),
                dosage_unit: q('oe-unit').value.trim(),
                usage_name: usage,
                usage_rate: rate,
                freq_code: q('oe-freq').value,
                freq_detail: q('oe-freq-detail').value.trim(),
                billing_flags: flags,
                category: this.category
            });
        });
        return { period, items };
    },

    async save(submit) {
        const data = this.collect();
        if (data.items.length === 0) { Dom.toast('请至少添加一条医嘱', 'error'); return; }
        if (data.items.some(it => !it.content)) { Dom.toast('医嘱内容不能为空', 'error'); return; }
        // 临时医嘱无频次/执行时间
        if (data.period === 'temp') {
            data.items.forEach(it => { it.freq_code = ''; it.freq_detail = ''; });
        }

        try {
            if (this.editId) {
                await AjaxLoader.api('orders/batch-delete', { method: 'POST', data: { ids: [this.editId] } });
            }
            const r = await AjaxLoader.api('orders/save', { method: 'POST', data: {
                patient_id: this.patientId,
                order_period: data.period,
                category: this.category,
                usage_name: data.items[0].usage_name,
                usage_rate: data.items[0].usage_rate,
                items: data.items,
                submit: submit ? 1 : 0
            }});
            Dom.toast(submit ? '医嘱已提交' : '已存为草稿', 'success');
            this.close();
            if (typeof OrderStation !== 'undefined') {
                const newId = r.data && r.data.id ? Number(r.data.id) : null;
                OrderStation.refresh(false, newId);
            }
        } catch (e) { /* AjaxLoader 已提示 */ }
    },

    close() {
        if (this.overlay && this.overlay.parentNode) this.overlay.parentNode.removeChild(this.overlay);
        this.overlay = null;
    }
};

window.OrderEditor = OrderEditor;
